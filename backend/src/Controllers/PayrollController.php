<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\SequenceHelper;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;
use Exception;
use PDO;

class PayrollController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $status = $request->getQuery('status');
        $year = $request->getQuery('year');

        $where = ["pb.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(pb.batch_number LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($status) {
            $where[] = "pb.status = :status";
            $params[':status'] = $status;
        }

        if ($year) {
            $where[] = "pb.year = :yr";
            $params[':yr'] = $year;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM payroll_batches pb WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT pb.*, u.name as creator_name, ap.name as approver_name,
                                          vr.voucher_number, COUNT(pi.id) as employee_count
                                   FROM payroll_batches pb
                                   LEFT JOIN users u ON pb.created_by = u.id
                                   LEFT JOIN users ap ON pb.approved_by = ap.id
                                   LEFT JOIN vouchers vr ON pb.voucher_id = vr.id
                                   LEFT JOIN payroll_items pi ON pb.id = pi.payroll_batch_id
                                   WHERE $whereSql
                                   GROUP BY pb.id
                                   ORDER BY pb.year DESC, pb.month DESC, pb.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $batches = $dataStmt->fetchAll();

        Response::success($batches, 'Payroll batches retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT pb.*, u.name as creator_name, ap.name as approver_name, vr.voucher_number
                               FROM payroll_batches pb
                               LEFT JOIN users u ON pb.created_by = u.id
                               LEFT JOIN users ap ON pb.approved_by = ap.id
                               LEFT JOIN vouchers vr ON pb.voucher_id = vr.id
                               WHERE pb.id = :id AND pb.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $batch = $stmt->fetch();
        if (!$batch) {
            Response::notFound("Payroll batch not found");
        }

        $itemsStmt = $pdo->prepare("SELECT pi.*, e.employee_code, e.first_name, e.last_name, e.bank_account,
                                           d.name as department_name, des.name as designation_name
                                    FROM payroll_items pi
                                    JOIN employees e ON pi.employee_id = e.id
                                    JOIN departments d ON e.department_id = d.id
                                    JOIN designations des ON e.designation_id = des.id
                                    WHERE pi.payroll_batch_id = :id
                                    ORDER BY e.first_name ASC");
        $itemsStmt->execute([':id' => $id]);
        $batch['items'] = $itemsStmt->fetchAll();

        Response::success($batch);
    }

    public function generate(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.payroll');
        $data = Validator::validate($request->body(), [
            'month' => 'required|integer',
            'year'  => 'required|integer',
        ]);

        $month = (int)$data['month'];
        $year = (int)$data['year'];
        $companyId = $request->getCompanyId();
        $branchId = $request->getBranchId();

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $daysInMonth = (int)date('t', strtotime($startDate));
        $endDate = sprintf('%04d-%02d-%02d', $year, $month, $daysInMonth);

        $pdo = Database::getConnection();

        // Check if batch for this month/year already exists
        $chk = $pdo->prepare("SELECT COUNT(*) FROM payroll_batches WHERE company_id = :cid AND month = :m AND year = :y AND status != 'cancelled'");
        $chk->execute([':cid' => $companyId, ':m' => $month, ':y' => $year]);
        if ((int)$chk->fetchColumn() > 0) {
            Response::error("An active payroll batch for $month/$year already exists.", 422);
        }

        // Fetch active employees
        $empStmt = $pdo->prepare("SELECT * FROM employees WHERE company_id = :cid AND is_active = 1 AND deleted_at IS NULL");
        $empStmt->execute([':cid' => $companyId]);
        $employees = $empStmt->fetchAll();

        if (empty($employees)) {
            Response::error("No active employees found to generate payroll.", 422);
        }

        Database::beginTransaction();
        try {
            $batchNum = SequenceHelper::getNextNumber($companyId, 'PAY', 'PAY');

            $totalBasic = 0.0;
            $totalAllowances = 0.0;
            $totalDeductions = 0.0;
            $totalNet = 0.0;

            $bStmt = $pdo->prepare("INSERT INTO payroll_batches (company_id, branch_id, batch_number, month, year, 
                                                               start_date, end_date, status, total_basic, total_allowances, 
                                                               total_deductions, net_salary, created_by) 
                                   VALUES (:cid, :bid, :num, :m, :y, :sdate, :edate, 'draft', 0, 0, 0, 0, :uid)");
            $bStmt->execute([
                ':cid'   => $companyId,
                ':bid'   => $branchId,
                ':num'   => $batchNum,
                ':m'     => $month,
                ':y'     => $year,
                ':sdate' => $startDate,
                ':edate' => $endDate,
                ':uid'   => $request->getUserId(),
            ]);
            $batchId = (int)$pdo->lastInsertId();

            $itemIns = $pdo->prepare("INSERT INTO payroll_items (payroll_batch_id, employee_id, basic_salary, allowances, 
                                                               deductions, net_salary, working_days, present_days, 
                                                               leave_days, absent_days, remarks) 
                                      VALUES (:bid, :eid, :basic, :allow, :ded, :net, :wdays, :pdays, :ldays, :adays, :rem)");

            foreach ($employees as $emp) {
                $eid = (int)$emp['id'];
                $basic = (float)$emp['basic_salary'];
                $allow = (float)$emp['house_rent'] + (float)$emp['medical_allowance'] + (float)$emp['other_allowance'];

                // 1. Count attendance present days
                $attStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance 
                                          WHERE employee_id = :eid AND date BETWEEN :sdate AND :edate 
                                            AND status IN ('present', 'late')");
                $attStmt->execute([':eid' => $eid, ':sdate' => $startDate, ':edate' => $endDate]);
                $presentDays = (int)$attStmt->fetchColumn();

                // 2. Count approved leave days
                $lStmt = $pdo->prepare("SELECT COALESCE(SUM(total_days), 0) FROM leave_requests 
                                        WHERE employee_id = :eid AND status = 'approved' 
                                          AND start_date <= :edate AND end_date >= :sdate");
                $lStmt->execute([':eid' => $eid, ':sdate' => $startDate, ':edate' => $endDate]);
                $leaveDays = (int)$lStmt->fetchColumn();

                // If no attendance records exist for month, assume full present by default
                $totalLogged = $presentDays + $leaveDays;
                if ($totalLogged === 0) {
                    $presentDays = $daysInMonth;
                    $absentDays = 0;
                } else {
                    $absentDays = max(0, $daysInMonth - ($presentDays + $leaveDays));
                }

                $deductions = round(($basic / $daysInMonth) * $absentDays, 2);
                $net = ($basic + $allow) - $deductions;

                $totalBasic += $basic;
                $totalAllowances += $allow;
                $totalDeductions += $deductions;
                $totalNet += $net;

                $itemIns->execute([
                    ':bid'   => $batchId,
                    ':eid'   => $eid,
                    ':basic' => $basic,
                    ':allow' => $allow,
                    ':ded'   => $deductions,
                    ':net'   => $net,
                    ':wdays' => $daysInMonth,
                    ':pdays' => $presentDays,
                    ':ldays' => $leaveDays,
                    ':adays' => $absentDays,
                    ':rem'   => $absentDays > 0 ? "$absentDays absent days deducted" : "Full month attendance",
                ]);
            }

            // Update batch summary totals
            $updB = $pdo->prepare("UPDATE payroll_batches SET total_basic = :tb, total_allowances = :ta, 
                                  total_deductions = :td, net_salary = :tnet, updated_at = NOW() WHERE id = :id");
            $updB->execute([
                ':tb'   => $totalBasic,
                ':ta'   => $totalAllowances,
                ':td'   => $totalDeductions,
                ':tnet' => $totalNet,
                ':id'   => $batchId,
            ]);

            Database::commit();
            AuditLogger::log($request, 'GENERATE_PAYROLL', 'Payroll', (string)$batchId, null, [
                'batch_number' => $batchNum,
                'net_salary'   => $totalNet,
                'month'        => $month,
                'year'         => $year,
            ]);

            Response::created([
                'id'           => $batchId,
                'batch_number' => $batchNum,
                'net_salary'   => $totalNet,
            ], "Payroll batch #$batchNum generated successfully for $month/$year");
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to generate payroll batch: ' . $e->getMessage(), 500);
        }
    }

    public function approve(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.payroll');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM payroll_batches WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $batch = $stmt->fetch();

        if (!$batch) {
            Response::notFound("Payroll batch not found");
        }
        if ($batch['status'] !== 'draft') {
            Response::error("Cannot approve a payroll batch with status '{$batch['status']}'.", 400);
        }

        Database::beginTransaction();
        try {
            $companyId = (int)$batch['company_id'];
            $branchId = (int)($batch['branch_id'] ?? $request->getBranchId());
            $netSalary = (float)$batch['net_salary'];

            // Accounts: Salaries & Wages Expense (5110, id 22), Salaries & Wages Payable (2020, id 10)
            $expAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '5110'");
            $expAccStmt->execute([':cid' => $companyId]);
            $salExpenseAccId = (int)($expAccStmt->fetchColumn() ?: 22);

            $payAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '2020'");
            $payAccStmt->execute([':cid' => $companyId]);
            $salPayableAccId = (int)($payAccStmt->fetchColumn() ?: 10);

            $vrNum = SequenceHelper::getNextNumber($companyId, 'VR', 'VR');
            $vrStmt = $pdo->prepare("INSERT INTO vouchers (company_id, branch_id, voucher_number, voucher_type, date, 
                                                          reference_type, reference_id, narration, total_debit, total_credit, 
                                                          status, created_by, posted_by, posted_at) 
                                     VALUES (:cid, :bid, :vnum, 'journal', CURRENT_DATE(), 'PAYROLL', :ref_id, :nar, :tot, :tot, 'posted', :uid, :uid, NOW())");
            $vrStmt->execute([
                ':cid'    => $companyId,
                ':bid'    => $branchId,
                ':vnum'   => $vrNum,
                ':ref_id' => $id,
                ':nar'    => "Salaries & wages expense for payroll batch #" . $batch['batch_number'] . " (" . $batch['month'] . "/" . $batch['year'] . ")",
                ':tot'    => $netSalary,
                ':uid'    => $request->getUserId(),
            ]);
            $voucherId = (int)$pdo->lastInsertId();

            $vlStmt = $pdo->prepare("INSERT INTO voucher_lines (voucher_id, account_id, party_type, party_id, debit, credit, description) 
                                     VALUES (:vid, :acc, 'none', NULL, :deb, :crd, :desc)");

            // Debit Salaries Expense
            $vlStmt->execute([
                ':vid'  => $voucherId,
                ':acc'  => $salExpenseAccId,
                ':deb'  => $netSalary,
                ':crd'  => 0,
                ':desc' => "Salaries & wages expense for batch #" . $batch['batch_number'],
            ]);

            // Credit Salaries Payable
            $vlStmt->execute([
                ':vid'  => $voucherId,
                ':acc'  => $salPayableAccId,
                ':deb'  => 0,
                ':crd'  => $netSalary,
                ':desc' => "Salaries liability payable for batch #" . $batch['batch_number'],
            ]);

            // Update batch status & link voucher
            $updB = $pdo->prepare("UPDATE payroll_batches SET status = 'approved', approved_by = :uid, approved_at = NOW(), 
                                  voucher_id = :vid, updated_at = NOW() WHERE id = :id");
            $updB->execute([
                ':uid' => $request->getUserId(),
                ':vid' => $voucherId,
                ':id'  => $id,
            ]);

            Database::commit();
            AuditLogger::log($request, 'APPROVE_PAYROLL', 'Payroll', (string)$id, null, [
                'batch_number'   => $batch['batch_number'],
                'voucher_number' => $vrNum,
                'net_salary'     => $netSalary,
            ]);

            Response::success([
                'voucher_id'     => $voucherId,
                'voucher_number' => $vrNum,
            ], "Payroll batch approved and expense voucher posted successfully");
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to approve payroll batch: ' . $e->getMessage(), 500);
        }
    }

    public function cancel(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.payroll');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM payroll_batches WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $status = $stmt->fetchColumn();

        if ($status !== 'draft') {
            Response::error("Only draft payroll batches can be cancelled.", 400);
        }

        $upd = $pdo->prepare("UPDATE payroll_batches SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
        $upd->execute([':id' => $id]);

        AuditLogger::log($request, 'CANCEL_PAYROLL', 'Payroll', (string)$id);
        Response::success(null, 'Payroll batch cancelled');
    }
}
