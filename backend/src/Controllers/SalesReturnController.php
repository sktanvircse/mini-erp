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

class SalesReturnController
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

        $where = ["srt.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(srt.return_number LIKE :srch OR c.name LIKE :srch OR srt.remarks LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($customerId) {
            $where[] = "srt.customer_id = :cust";
            $params[':cust'] = $customerId;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_returns srt JOIN customers c ON srt.customer_id = c.id WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT srt.*, c.name as customer_name, c.code as customer_code,
                                          w.name as warehouse_name, inv.invoice_number, vr.voucher_number
                                   FROM sales_returns srt
                                   JOIN customers c ON srt.customer_id = c.id
                                   JOIN warehouses w ON srt.warehouse_id = w.id
                                   LEFT JOIN invoices inv ON srt.invoice_id = inv.id
                                   LEFT JOIN vouchers vr ON srt.voucher_id = vr.id
                                   WHERE $whereSql
                                   ORDER BY srt.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $returns = $dataStmt->fetchAll();

        Response::success($returns, 'Sales returns retrieved', 200, [
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

        $stmt = $pdo->prepare("SELECT srt.*, c.name as customer_name, c.code as customer_code,
                                      w.name as warehouse_name, inv.invoice_number, vr.voucher_number
                               FROM sales_returns srt
                               JOIN customers c ON srt.customer_id = c.id
                               JOIN warehouses w ON srt.warehouse_id = w.id
                               LEFT JOIN invoices inv ON srt.invoice_id = inv.id
                               LEFT JOIN vouchers vr ON srt.voucher_id = vr.id
                               WHERE srt.id = :id AND srt.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $return = $stmt->fetch();
        if (!$return) {
            Response::notFound("Sales return not found");
        }

        $itemsStmt = $pdo->prepare("SELECT srti.*, p.code as product_code, p.name as product_name, u.symbol as unit_symbol
                                    FROM sales_return_items srti
                                    JOIN products p ON srti.product_id = p.id
                                    LEFT JOIN units u ON p.unit_id = u.id
                                    WHERE srti.return_id = :id");
        $itemsStmt->execute([':id' => $id]);
        $return['items'] = $itemsStmt->fetchAll();

        Response::success($return);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.return');
        $data = Validator::validate($request->body(), [
            'customer_id'  => 'required|integer',
            'warehouse_id' => 'required|integer',
            'date'         => 'required',
            'items'        => 'required',
        ]);

        $items = $request->get('items');
        if (!is_array($items) || empty($items)) {
            Response::error("Return must include at least one item.", 422);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $companyId = $request->getCompanyId();
            $branchId = $request->getBranchId();
            $customerId = (int)$data['customer_id'];
            $warehouseId = (int)$data['warehouse_id'];
            $returnDate = $data['date'];
            $invoiceId = $request->get('invoice_id') ? (int)$request->get('invoice_id') : null;

            $totalAmount = 0.0;
            $totalCost = 0.0;

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $totalAmount += ($qty * $price);

                $pCostStmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = :pid");
                $pCostStmt->execute([':pid' => $item['product_id']]);
                $cPrice = (float)$pCostStmt->fetchColumn();
                $totalCost += ($qty * $cPrice);
            }

            if ($totalAmount <= 0) {
                throw new Exception("Return total amount must be greater than zero.");
            }

            $retNum = SequenceHelper::getNextNumber($companyId, 'SRT', 'SRT');

            $retStmt = $pdo->prepare("INSERT INTO sales_returns (company_id, branch_id, return_number, invoice_id, 
                                                               customer_id, warehouse_id, date, status, total_amount, 
                                                               remarks, created_by) 
                                      VALUES (:cid, :bid, :num, :invid, :cust, :wid, :dt, 'approved', :tot, :rem, :uid)");
            $retStmt->execute([
                ':cid'   => $companyId,
                ':bid'   => $branchId,
                ':num'   => $retNum,
                ':invid' => $invoiceId,
                ':cust'  => $customerId,
                ':wid'   => $warehouseId,
                ':dt'    => $returnDate,
                ':tot'   => $totalAmount,
                ':rem'   => $request->get('remarks'),
                ':uid'   => $request->getUserId(),
            ]);
            $returnId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO sales_return_items (return_id, product_id, quantity, unit_price, total_price) 
                                       VALUES (:rid, :pid, :qty, :price, :tot)");

            $sbStmt = $pdo->prepare("INSERT INTO stock_balances (company_id, warehouse_id, product_id, quantity, avg_unit_cost) 
                                     VALUES (:cid, :wid, :pid, :qty, :cost) 
                                     ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = NOW()");

            $slStmt = $pdo->prepare("INSERT INTO stock_ledger (company_id, branch_id, warehouse_id, product_id, date, 
                                                             reference_type, reference_id, qty_in, qty_out, unit_cost, total_cost, remarks) 
                                     VALUES (:cid, :bid, :wid, :pid, :dt, 'SALES_RETURN', :ref_id, :qin, 0, :cost, :tot, :rem)");

            $updProdStock = $pdo->prepare("UPDATE products SET current_stock = current_stock + :qty WHERE id = :pid");

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (float)$item['quantity'];
                $price = (float)$item['unit_price'];
                $lineTotal = $qty * $price;

                if ($qty <= 0) continue;

                // 1. Return stock to warehouse balance
                $sbStmt->execute([
                    ':cid'  => $companyId,
                    ':wid'  => $warehouseId,
                    ':pid'  => $productId,
                    ':qty'  => $qty,
                    ':cost' => $price,
                ]);

                // 2. Increase product total stock
                $updProdStock->execute([':qty' => $qty, ':pid' => $productId]);

                // 3. Insert Return Item
                $itemStmt->execute([
                    ':rid'   => $returnId,
                    ':pid'   => $productId,
                    ':qty'   => $qty,
                    ':price' => $price,
                    ':tot'   => $lineTotal,
                ]);

                // 4. Insert Stock Ledger
                $slStmt->execute([
                    ':cid'    => $companyId,
                    ':bid'    => $branchId,
                    ':wid'    => $warehouseId,
                    ':pid'    => $productId,
                    ':dt'     => $returnDate,
                    ':ref_id' => $returnId,
                    ':qin'    => $qty,
                    ':cost'   => $price,
                    ':tot'    => $lineTotal,
                    ':rem'    => "Sales return #$retNum from customer #$customerId",
                ]);
            }

            // Create Reversal Accounting Voucher
            // Debit: Sales Returns / Revenue (4010 or 4020)
            // Credit: Customer Receivable (1030)
            $salesAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '4010'");
            $salesAccStmt->execute([':cid' => $companyId]);
            $salesAccountId = (int)($salesAccStmt->fetchColumn() ?: 16);

            $arAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '1030'");
            $arAccStmt->execute([':cid' => $companyId]);
            $arAccountId = (int)($arAccStmt->fetchColumn() ?: 4);

            $vrNum = SequenceHelper::getNextNumber($companyId, 'VR', 'VR');
            $vrStmt = $pdo->prepare("INSERT INTO vouchers (company_id, branch_id, voucher_number, voucher_type, date, 
                                                          reference_type, reference_id, narration, total_debit, total_credit, 
                                                          status, created_by, posted_by, posted_at) 
                                     VALUES (:cid, :bid, :vnum, 'sales', :dt, 'SALES_RETURN', :ref_id, :nar, :tot, :tot, 'posted', :uid, :uid, NOW())");
            $vrStmt->execute([
                ':cid'    => $companyId,
                ':bid'    => $branchId,
                ':vnum'   => $vrNum,
                ':dt'     => $returnDate,
                ':ref_id' => $returnId,
                ':nar'    => "Reversal voucher for sales return #$retNum",
                ':tot'    => $totalAmount,
                ':uid'    => $request->getUserId(),
            ]);
            $voucherId = (int)$pdo->lastInsertId();

            $vlStmt = $pdo->prepare("INSERT INTO voucher_lines (voucher_id, account_id, party_type, party_id, debit, credit, description) 
                                     VALUES (:vid, :acc, :ptype, :pid, :deb, :crd, :desc)");

            // Debit Sales Revenue (reverses sales income)
            $vlStmt->execute([
                ':vid'   => $voucherId,
                ':acc'   => $salesAccountId,
                ':ptype' => 'none',
                ':pid'   => null,
                ':deb'   => $totalAmount,
                ':crd'   => 0,
                ':desc'  => "Sales revenue reversal from return #$retNum",
            ]);

            // Credit Customer Receivable (reverses receivable)
            $vlStmt->execute([
                ':vid'   => $voucherId,
                ':acc'   => $arAccountId,
                ':ptype' => 'customer',
                ':pid'   => $customerId,
                ':deb'   => 0,
                ':crd'   => $totalAmount,
                ':desc'  => "Receivable credit for sales return #$retNum",
            ]);

            // Deduct customer current_balance (reduces receivable)
            $updCust = $pdo->prepare("UPDATE customers SET current_balance = current_balance - :amt, updated_at = NOW() WHERE id = :cust");
            $updCust->execute([':amt' => $totalAmount, ':cust' => $customerId]);

            // Link Voucher
            $updSrtV = $pdo->prepare("UPDATE sales_returns SET voucher_id = :vid WHERE id = :id");
            $updSrtV->execute([':vid' => $voucherId, ':id' => $returnId]);

            Database::commit();
            AuditLogger::log($request, 'SALES_RETURN', 'SalesReturn', (string)$returnId, null, [
                'return_number'  => $retNum,
                'voucher_number' => $vrNum,
                'total_amount'   => $totalAmount,
            ]);

            Response::created([
                'id'             => $returnId,
                'return_number'  => $retNum,
                'voucher_id'     => $voucherId,
                'voucher_number' => $vrNum,
                'total_amount'   => $totalAmount,
            ], 'Sales return processed, stock added back and receivable reversed successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to process sales return: ' . $e->getMessage(), 500);
        }
    }
}
