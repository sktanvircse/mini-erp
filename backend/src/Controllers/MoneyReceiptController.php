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

class MoneyReceiptController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $customerId = $request->getQuery('customer_id');

        $where = ["mr.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(mr.receipt_number LIKE :srch OR c.name LIKE :srch OR mr.reference_no LIKE :srch OR mr.remarks LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($customerId) {
            $where[] = "mr.customer_id = :cust";
            $params[':cust'] = $customerId;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM money_receipts mr JOIN customers c ON mr.customer_id = c.id WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT mr.*, c.name as customer_name, c.code as customer_code,
                                          coa.name as deposit_account_name, vr.voucher_number, u.name as creator_name
                                   FROM money_receipts mr
                                   JOIN customers c ON mr.customer_id = c.id
                                   JOIN chart_of_accounts coa ON mr.deposit_account_id = coa.id
                                   LEFT JOIN vouchers vr ON mr.voucher_id = vr.id
                                   LEFT JOIN users u ON mr.created_by = u.id
                                   WHERE $whereSql
                                   ORDER BY mr.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $receipts = $dataStmt->fetchAll();

        Response::success($receipts, 'Money receipts retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT mr.*, c.name as customer_name, c.code as customer_code, c.email as customer_email,
                                      coa.name as deposit_account_name, vr.voucher_number, u.name as creator_name
                               FROM money_receipts mr
                               JOIN customers c ON mr.customer_id = c.id
                               JOIN chart_of_accounts coa ON mr.deposit_account_id = coa.id
                               LEFT JOIN vouchers vr ON mr.voucher_id = vr.id
                               LEFT JOIN users u ON mr.created_by = u.id
                               WHERE mr.id = :id AND mr.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $receipt = $stmt->fetch();
        if (!$receipt) {
            Response::notFound("Money receipt not found");
        }

        $allocStmt = $pdo->prepare("SELECT mri.*, inv.invoice_number, inv.total_amount as invoice_total, inv.date as invoice_date 
                                    FROM money_receipt_invoices mri
                                    JOIN invoices inv ON mri.invoice_id = inv.id
                                    WHERE mri.money_receipt_id = :id");
        $allocStmt->execute([':id' => $id]);
        $receipt['allocations'] = $allocStmt->fetchAll();

        Response::success($receipt);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.receipt');
        $data = Validator::validate($request->body(), [
            'customer_id'        => 'required|integer',
            'date'               => 'required',
            'amount'             => 'required|numeric',
            'deposit_account_id' => 'required|integer',
            'payment_method'     => 'required|in:cash,bank,cheque,other',
        ]);

        $amount = (float)$data['amount'];
        if ($amount <= 0) {
            Response::error("Payment amount must be greater than zero.", 422);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $companyId = $request->getCompanyId();
            $branchId = $request->getBranchId();
            $customerId = (int)$data['customer_id'];
            $receiptDate = $data['date'];
            $depositAccountId = (int)$data['deposit_account_id'];

            $mrNum = SequenceHelper::getNextNumber($companyId, 'MR', 'MR');

            $mrStmt = $pdo->prepare("INSERT INTO money_receipts (company_id, branch_id, receipt_number, customer_id, 
                                                               date, payment_method, reference_no, deposit_account_id, 
                                                               amount, status, remarks, created_by) 
                                     VALUES (:cid, :bid, :num, :cust, :dt, :pm, :ref, :acc, :amt, 'approved', :rem, :uid)");
            $mrStmt->execute([
                ':cid'  => $companyId,
                ':bid'  => $branchId,
                ':num'  => $mrNum,
                ':cust' => $customerId,
                ':dt'   => $receiptDate,
                ':pm'   => $data['payment_method'],
                ':ref'  => $request->get('reference_no'),
                ':acc'  => $depositAccountId,
                ':amt'  => $amount,
                ':rem'  => $request->get('remarks'),
                ':uid'  => $request->getUserId(),
            ]);
            $receiptId = (int)$pdo->lastInsertId();

            // Invoice allocations
            $allocations = $request->get('invoices', []);
            $allocatedTotal = 0.0;

            if (is_array($allocations) && !empty($allocations)) {
                $insAlloc = $pdo->prepare("INSERT INTO money_receipt_invoices (money_receipt_id, invoice_id, allocated_amount) 
                                           VALUES (:mrid, :invid, :amt)");
                $updInv = $pdo->prepare("UPDATE invoices 
                                         SET paid_amount = paid_amount + :amt, 
                                             balance_amount = total_amount - (paid_amount + :amt2),
                                             status = CASE WHEN (total_amount - (paid_amount + :amt3)) <= 0.001 THEN 'paid' ELSE 'partially_paid' END,
                                             updated_at = NOW() 
                                         WHERE id = :invid");

                foreach ($allocations as $alloc) {
                    $allocAmt = (float)($alloc['amount'] ?? 0);
                    $invId = (int)($alloc['invoice_id'] ?? 0);
                    if ($allocAmt <= 0 || $invId <= 0) continue;

                    $insAlloc->execute([
                        ':mrid'  => $receiptId,
                        ':invid' => $invId,
                        ':amt'   => $allocAmt,
                    ]);

                    $updInv->execute([
                        ':amt'   => $allocAmt,
                        ':amt2'  => $allocAmt,
                        ':amt3'  => $allocAmt,
                        ':invid' => $invId,
                    ]);

                    $allocatedTotal += $allocAmt;
                }
            }

            // Create Double-Entry Accounting Voucher: Receipt Voucher
            // Debit: Deposit Account (Cash or Bank)
            // Credit: Customer Receivable (1030)
            $arAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '1030'");
            $arAccStmt->execute([':cid' => $companyId]);
            $arAccountId = (int)($arAccStmt->fetchColumn() ?: 4);

            $vrNum = SequenceHelper::getNextNumber($companyId, 'VR', 'VR');
            $vrStmt = $pdo->prepare("INSERT INTO vouchers (company_id, branch_id, voucher_number, voucher_type, date, 
                                                          reference_type, reference_id, narration, total_debit, total_credit, 
                                                          status, created_by, posted_by, posted_at) 
                                     VALUES (:cid, :bid, :vnum, 'receipt', :dt, 'MONEY_RECEIPT', :ref_id, :nar, :tot, :tot, 'posted', :uid, :uid, NOW())");
            $vrStmt->execute([
                ':cid'    => $companyId,
                ':bid'    => $branchId,
                ':vnum'   => $vrNum,
                ':dt'     => $receiptDate,
                ':ref_id' => $receiptId,
                ':nar'    => "Customer payment receipt #$mrNum",
                ':tot'    => $amount,
                ':uid'    => $request->getUserId(),
            ]);
            $voucherId = (int)$pdo->lastInsertId();

            $vlStmt = $pdo->prepare("INSERT INTO voucher_lines (voucher_id, account_id, party_type, party_id, debit, credit, description) 
                                     VALUES (:vid, :acc, :ptype, :pid, :deb, :crd, :desc)");

            // Line 1: Debit Cash/Bank
            $vlStmt->execute([
                ':vid'   => $voucherId,
                ':acc'   => $depositAccountId,
                ':ptype' => 'none',
                ':pid'   => null,
                ':deb'   => $amount,
                ':crd'   => 0,
                ':desc'  => "Funds deposited via receipt #$mrNum",
            ]);

            // Line 2: Credit Customer Receivable
            $vlStmt->execute([
                ':vid'   => $voucherId,
                ':acc'   => $arAccountId,
                ':ptype' => 'customer',
                ':pid'   => $customerId,
                ':deb'   => 0,
                ':crd'   => $amount,
                ':desc'  => "Receivable settled from receipt #$mrNum",
            ]);

            // Deduct customer current_balance (Reduces receivable)
            $updCust = $pdo->prepare("UPDATE customers SET current_balance = current_balance - :amt, updated_at = NOW() WHERE id = :cust");
            $updCust->execute([':amt' => $amount, ':cust' => $customerId]);

            // Link Voucher to Money Receipt
            $updMrV = $pdo->prepare("UPDATE money_receipts SET voucher_id = :vid WHERE id = :id");
            $updMrV->execute([':vid' => $voucherId, ':id' => $receiptId]);

            Database::commit();
            AuditLogger::log($request, 'CREATE_RECEIPT', 'MoneyReceipt', (string)$receiptId, null, [
                'receipt_number' => $mrNum,
                'voucher_number' => $vrNum,
                'amount'         => $amount,
            ]);

            Response::created([
                'id'             => $receiptId,
                'receipt_number' => $mrNum,
                'voucher_id'     => $voucherId,
                'voucher_number' => $vrNum,
                'amount'         => $amount,
            ], 'Money receipt recorded, invoices allocated, and voucher posted successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to record money receipt: ' . $e->getMessage(), 500);
        }
    }
}
