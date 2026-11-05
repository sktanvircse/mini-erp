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

class VoucherController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $status = $request->getQuery('status');
        $type = $request->getQuery('voucher_type');
        $startDate = $request->getQuery('start_date');
        $endDate = $request->getQuery('end_date');

        $where = ["v.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(v.voucher_number LIKE :srch OR v.narration LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($status) {
            $where[] = "v.status = :status";
            $params[':status'] = $status;
        }

        if ($type) {
            $where[] = "v.voucher_type = :vtype";
            $params[':vtype'] = $type;
        }

        if ($startDate) {
            $where[] = "v.date >= :sdate";
            $params[':sdate'] = $startDate;
        }

        if ($endDate) {
            $where[] = "v.date <= :edate";
            $params[':edate'] = $endDate;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM vouchers v WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT v.*, u.name as creator_name, p.name as poster_name,
                                          COUNT(vl.id) as line_count
                                   FROM vouchers v
                                   LEFT JOIN users u ON v.created_by = u.id
                                   LEFT JOIN users p ON v.posted_by = p.id
                                   LEFT JOIN voucher_lines vl ON v.id = vl.voucher_id
                                   WHERE $whereSql
                                   GROUP BY v.id
                                   ORDER BY v.date DESC, v.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $vouchers = $dataStmt->fetchAll();

        Response::success($vouchers, 'Vouchers retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT v.*, u.name as creator_name, p.name as poster_name 
                               FROM vouchers v
                               LEFT JOIN users u ON v.created_by = u.id
                               LEFT JOIN users p ON v.posted_by = p.id
                               WHERE v.id = :id AND v.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $voucher = $stmt->fetch();
        if (!$voucher) {
            Response::notFound("Voucher not found");
        }

        $linesStmt = $pdo->prepare("SELECT vl.*, coa.code as account_code, coa.name as account_name, coa.type as account_type,
                                           CASE 
                                             WHEN vl.party_type = 'customer' THEN (SELECT c.name FROM customers c WHERE c.id = vl.party_id)
                                             WHEN vl.party_type = 'supplier' THEN (SELECT s.name FROM suppliers s WHERE s.id = vl.party_id)
                                             WHEN vl.party_type = 'employee' THEN (SELECT CONCAT(e.first_name, ' ', e.last_name) FROM employees e WHERE e.id = vl.party_id)
                                             ELSE NULL 
                                           END as party_name
                                    FROM voucher_lines vl
                                    JOIN chart_of_accounts coa ON vl.account_id = coa.id
                                    WHERE vl.voucher_id = :id
                                    ORDER BY vl.id ASC");
        $linesStmt->execute([':id' => $id]);
        $voucher['lines'] = $linesStmt->fetchAll();

        Response::success($voucher);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.voucher');
        $data = Validator::validate($request->body(), [
            'voucher_type' => 'required|in:journal,payment,receipt,sales,purchase',
            'date'         => 'required',
            'lines'        => 'required',
        ]);

        $voucherDate = $data['date'];
        $companyId = $request->getCompanyId();
        $pdo = Database::getConnection();

        // Check if date falls in a closed financial period
        $this->assertOpenPeriod($pdo, $companyId, $voucherDate);

        $lines = $request->get('lines');
        if (!is_array($lines) || count($lines) < 2) {
            Response::error("A voucher requires at least two line entries.", 422);
        }

        // Rule 13: Every voucher must balance (total debit equals total credit). Reject it otherwise.
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as $line) {
            $totalDebit += (float)($line['debit'] ?? 0);
            $totalCredit += (float)($line['credit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.001) {
            Response::error(sprintf(
                "Voucher is unbalanced! Total Debit ($%.2f) must equal Total Credit ($%.2f). Difference: $%.2f",
                $totalDebit,
                $totalCredit,
                abs($totalDebit - $totalCredit)
            ), 422);
        }

        if ($totalDebit <= 0) {
            Response::error("Voucher amount must be greater than zero.", 422);
        }

        Database::beginTransaction();
        try {
            $vNum = SequenceHelper::getNextNumber($companyId, 'VR', 'VR');
            $status = $request->get('post_now') ? 'posted' : 'unposted';

            $stmt = $pdo->prepare("INSERT INTO vouchers (company_id, branch_id, voucher_number, voucher_type, date, 
                                                       narration, total_debit, total_credit, status, created_by, posted_by, posted_at) 
                                   VALUES (:cid, :bid, :num, :type, :dt, :nar, :deb, :crd, :st, :uid, :puid, :pat)");
            $stmt->execute([
                ':cid'  => $companyId,
                ':bid'  => $request->getBranchId(),
                ':num'  => $vNum,
                ':type' => $data['voucher_type'],
                ':dt'   => $voucherDate,
                ':nar'  => $request->get('narration'),
                ':deb'  => $totalDebit,
                ':crd'  => $totalCredit,
                ':st'   => $status,
                ':uid'  => $request->getUserId(),
                ':puid' => $status === 'posted' ? $request->getUserId() : null,
                ':pat'  => $status === 'posted' ? date('Y-m-d H:i:s') : null,
            ]);
            $voucherId = (int)$pdo->lastInsertId();

            $lineStmt = $pdo->prepare("INSERT INTO voucher_lines (voucher_id, account_id, party_type, party_id, debit, credit, description) 
                                       VALUES (:vid, :acc, :ptype, :pid, :deb, :crd, :desc)");

            foreach ($lines as $line) {
                $deb = (float)($line['debit'] ?? 0);
                $crd = (float)($line['credit'] ?? 0);
                if ($deb <= 0 && $crd <= 0) continue;

                $lineStmt->execute([
                    ':vid'   => $voucherId,
                    ':acc'   => $line['account_id'],
                    ':ptype' => $line['party_type'] ?? 'none',
                    ':pid'   => !empty($line['party_id']) ? (int)$line['party_id'] : null,
                    ':deb'   => $deb,
                    ':crd'   => $crd,
                    ':desc'  => $line['description'] ?? null,
                ]);
            }

            Database::commit();
            AuditLogger::log($request, 'CREATE_VOUCHER', 'Voucher', (string)$voucherId, null, [
                'number' => $vNum,
                'total'  => $totalDebit,
                'status' => $status,
            ]);

            Response::created(['id' => $voucherId, 'voucher_number' => $vNum], 'Voucher saved successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to save voucher: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.voucher');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $voucher = $stmt->fetch();

        if (!$voucher) {
            Response::notFound("Voucher not found");
        }

        // Rule 14: Block edits to posted vouchers and to any date in a closed financial period.
        if ($voucher['status'] === 'posted') {
            Response::error("Cannot edit a posted voucher. Posted vouchers are immutable.", 403);
        }

        $voucherDate = $request->get('date', $voucher['date']);
        $this->assertOpenPeriod($pdo, (int)$voucher['company_id'], $voucherDate);

        $lines = $request->get('lines');
        if (!is_array($lines) || count($lines) < 2) {
            Response::error("A voucher requires at least two lines.", 422);
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as $line) {
            $totalDebit += (float)($line['debit'] ?? 0);
            $totalCredit += (float)($line['credit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.001) {
            Response::error("Voucher total debit must equal total credit.", 422);
        }

        Database::beginTransaction();
        try {
            $updV = $pdo->prepare("UPDATE vouchers SET voucher_type = :type, date = :dt, narration = :nar, 
                                  total_debit = :deb, total_credit = :crd, updated_at = NOW() WHERE id = :id");
            $updV->execute([
                ':type' => $request->get('voucher_type', $voucher['voucher_type']),
                ':dt'   => $voucherDate,
                ':nar'  => $request->get('narration', $voucher['narration']),
                ':deb'  => $totalDebit,
                ':crd'  => $totalCredit,
                ':id'   => $id,
            ]);

            $delLines = $pdo->prepare("DELETE FROM voucher_lines WHERE voucher_id = :id");
            $delLines->execute([':id' => $id]);

            $lineStmt = $pdo->prepare("INSERT INTO voucher_lines (voucher_id, account_id, party_type, party_id, debit, credit, description) 
                                       VALUES (:vid, :acc, :ptype, :pid, :deb, :crd, :desc)");

            foreach ($lines as $line) {
                $deb = (float)($line['debit'] ?? 0);
                $crd = (float)($line['credit'] ?? 0);
                if ($deb <= 0 && $crd <= 0) continue;

                $lineStmt->execute([
                    ':vid'   => $id,
                    ':acc'   => $line['account_id'],
                    ':ptype' => $line['party_type'] ?? 'none',
                    ':pid'   => !empty($line['party_id']) ? (int)$line['party_id'] : null,
                    ':deb'   => $deb,
                    ':crd'   => $crd,
                    ':desc'  => $line['description'] ?? null,
                ]);
            }

            Database::commit();
            AuditLogger::log($request, 'UPDATE_VOUCHER', 'Voucher', (string)$id);
            Response::success(null, 'Voucher updated successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to update voucher: ' . $e->getMessage(), 500);
        }
    }

    public function postVoucher(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.post');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM vouchers WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $voucher = $stmt->fetch();

        if (!$voucher) {
            Response::notFound("Voucher not found");
        }
        if ($voucher['status'] === 'posted') {
            Response::error("Voucher is already posted.", 400);
        }

        $this->assertOpenPeriod($pdo, (int)$voucher['company_id'], $voucher['date']);

        $upd = $pdo->prepare("UPDATE vouchers SET status = 'posted', posted_by = :uid, posted_at = NOW(), updated_at = NOW() WHERE id = :id");
        $upd->execute([':uid' => $request->getUserId(), ':id' => $id]);

        AuditLogger::log($request, 'POST_VOUCHER', 'Voucher', (string)$id, null, ['status' => 'posted']);
        Response::success(null, 'Voucher posted successfully');
    }

    public function cancel(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.post');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM vouchers WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $status = $stmt->fetchColumn();

        if ($status === 'posted') {
            Response::error("Cannot cancel a posted voucher directly. A reversing voucher must be created.", 403);
        }

        $upd = $pdo->prepare("UPDATE vouchers SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
        $upd->execute([':id' => $id]);

        AuditLogger::log($request, 'CANCEL_VOUCHER', 'Voucher', (string)$id);
        Response::success(null, 'Voucher cancelled');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.voucher');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM vouchers WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $status = $stmt->fetchColumn();

        if ($status === 'posted') {
            Response::error("Cannot delete a posted voucher. Posted vouchers are immutable.", 403);
        }

        $del = $pdo->prepare("DELETE FROM vouchers WHERE id = :id");
        $del->execute([':id' => $id]);

        AuditLogger::log($request, 'DELETE_VOUCHER', 'Voucher', (string)$id);
        Response::success(null, 'Voucher deleted');
    }

    private function assertOpenPeriod(PDO $pdo, int $companyId, string $date): void
    {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM financial_periods 
                               WHERE company_id = :cid AND :dt BETWEEN start_date AND end_date AND status = 'closed'");
        $stmt->execute([':cid' => $companyId, ':dt' => $date]);
        if ((int)$stmt->fetchColumn() > 0) {
            Response::error("Action blocked: Transaction date ($date) falls within a closed financial period.", 403);
        }
    }
}
