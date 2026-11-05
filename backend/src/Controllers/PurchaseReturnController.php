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

class PurchaseReturnController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $supplierId = $request->getQuery('supplier_id');

        $where = ["prt.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(prt.return_number LIKE :srch OR s.name LIKE :srch OR prt.remarks LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($supplierId) {
            $where[] = "prt.supplier_id = :sup";
            $params[':sup'] = $supplierId;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_returns prt JOIN suppliers s ON prt.supplier_id = s.id WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT prt.*, s.name as supplier_name, s.code as supplier_code,
                                          w.name as warehouse_name, grn.grn_number, vr.voucher_number
                                   FROM purchase_returns prt
                                   JOIN suppliers s ON prt.supplier_id = s.id
                                   JOIN warehouses w ON prt.warehouse_id = w.id
                                   LEFT JOIN goods_receipts grn ON prt.grn_id = grn.id
                                   LEFT JOIN vouchers vr ON prt.voucher_id = vr.id
                                   WHERE $whereSql
                                   ORDER BY prt.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $returns = $dataStmt->fetchAll();

        Response::success($returns, 'Purchase returns retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT prt.*, s.name as supplier_name, s.code as supplier_code,
                                      w.name as warehouse_name, grn.grn_number, vr.voucher_number
                               FROM purchase_returns prt
                               JOIN suppliers s ON prt.supplier_id = s.id
                               JOIN warehouses w ON prt.warehouse_id = w.id
                               LEFT JOIN goods_receipts grn ON prt.grn_id = grn.id
                               LEFT JOIN vouchers vr ON prt.voucher_id = vr.id
                               WHERE prt.id = :id AND prt.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $return = $stmt->fetch();
        if (!$return) {
            Response::notFound("Purchase return not found");
        }

        $itemsStmt = $pdo->prepare("SELECT prti.*, p.code as product_code, p.name as product_name, u.symbol as unit_symbol
                                    FROM purchase_return_items prti
                                    JOIN products p ON prti.product_id = p.id
                                    LEFT JOIN units u ON p.unit_id = u.id
                                    WHERE prti.return_id = :id");
        $itemsStmt->execute([':id' => $id]);
        $return['items'] = $itemsStmt->fetchAll();

        Response::success($return);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.return');
        $data = Validator::validate($request->body(), [
            'supplier_id'  => 'required|integer',
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
            $supplierId = (int)$data['supplier_id'];
            $warehouseId = (int)$data['warehouse_id'];
            $returnDate = $data['date'];
            $grnId = $request->get('grn_id') ?: null;

            $totalAmount = 0.0;
            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $totalAmount += ($qty * $price);
            }

            if ($totalAmount <= 0) {
                throw new Exception("Return total amount must be greater than zero.");
            }

            $retNum = SequenceHelper::getNextNumber($companyId, 'PRT', 'PRT');

            $retStmt = $pdo->prepare("INSERT INTO purchase_returns (company_id, branch_id, return_number, grn_id, supplier_id, 
                                                                  warehouse_id, date, status, total_amount, remarks, created_by) 
                                      VALUES (:cid, :bid, :num, :grnid, :sid, :wid, :dt, 'approved', :tot, :rem, :uid)");
            $retStmt->execute([
                ':cid'   => $companyId,
                ':bid'   => $branchId,
                ':num'   => $retNum,
                ':grnid' => $grnId,
                ':sid'   => $supplierId,
                ':wid'   => $warehouseId,
                ':dt'    => $returnDate,
                ':tot'   => $totalAmount,
                ':rem'   => $request->get('remarks'),
                ':uid'   => $request->getUserId(),
            ]);
            $returnId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO purchase_return_items (return_id, product_id, quantity, unit_price, total_price) 
                                       VALUES (:rid, :pid, :qty, :price, :tot)");

            $slStmt = $pdo->prepare("INSERT INTO stock_ledger (company_id, branch_id, warehouse_id, product_id, date, 
                                                             reference_type, reference_id, qty_in, qty_out, unit_cost, total_cost, remarks) 
                                     VALUES (:cid, :bid, :wid, :pid, :dt, 'PURCHASE_RETURN', :ref_id, 0, :qout, :cost, :tot, :rem)");

            $updProdStock = $pdo->prepare("UPDATE products SET current_stock = current_stock - :qty WHERE id = :pid");

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (float)$item['quantity'];
                $price = (float)$item['unit_price'];
                $lineTotal = $qty * $price;

                if ($qty <= 0) continue;

                // Check stock availability in warehouse
                $sbCheck = $pdo->prepare("SELECT quantity FROM stock_balances 
                                          WHERE company_id = :cid AND warehouse_id = :wid AND product_id = :pid FOR UPDATE");
                $sbCheck->execute([':cid' => $companyId, ':wid' => $warehouseId, ':pid' => $productId]);
                $avail = (float)$sbCheck->fetchColumn();

                if ($avail < $qty) {
                    throw new Exception("Cannot return product ID #$productId: available stock ($avail) is less than return qty ($qty).");
                }

                // Deduct stock balance
                $updSb = $pdo->prepare("UPDATE stock_balances SET quantity = quantity - :qty, updated_at = NOW() 
                                        WHERE company_id = :cid AND warehouse_id = :wid AND product_id = :pid");
                $updSb->execute([':qty' => $qty, ':cid' => $companyId, ':wid' => $warehouseId, ':pid' => $productId]);

                // Update product current_stock
                $updProdStock->execute([':qty' => $qty, ':pid' => $productId]);

                // Insert Return Item
                $itemStmt->execute([
                    ':rid'   => $returnId,
                    ':pid'   => $productId,
                    ':qty'   => $qty,
                    ':price' => $price,
                    ':tot'   => $lineTotal,
                ]);

                // Insert Stock Ledger
                $slStmt->execute([
                    ':cid'    => $companyId,
                    ':bid'    => $branchId,
                    ':wid'    => $warehouseId,
                    ':pid'    => $productId,
                    ':dt'     => $returnDate,
                    ':ref_id' => $returnId,
                    ':qout'   => $qty,
                    ':cost'   => $price,
                    ':tot'    => $lineTotal,
                    ':rem'    => "Purchase return #$retNum to supplier #$supplierId",
                ]);
            }

            // Create Reversal Voucher: Debit Supplier Payable, Credit Inventory Asset
            $invAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '1040'");
            $invAccStmt->execute([':cid' => $companyId]);
            $inventoryAccountId = (int)($invAccStmt->fetchColumn() ?: 5);

            $payAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '2010'");
            $payAccStmt->execute([':cid' => $companyId]);
            $payableAccountId = (int)($payAccStmt->fetchColumn() ?: 9);

            $vrNum = SequenceHelper::getNextNumber($companyId, 'VR', 'VR');
            $vrStmt = $pdo->prepare("INSERT INTO vouchers (company_id, branch_id, voucher_number, voucher_type, date, 
                                                          reference_type, reference_id, narration, total_debit, total_credit, 
                                                          status, created_by, posted_by, posted_at) 
                                     VALUES (:cid, :bid, :vnum, 'purchase', :dt, 'PURCHASE_RETURN', :ref_id, :nar, :tot, :tot, 'posted', :uid, :uid, NOW())");
            $vrStmt->execute([
                ':cid'    => $companyId,
                ':bid'    => $branchId,
                ':vnum'   => $vrNum,
                ':dt'     => $returnDate,
                ':ref_id' => $returnId,
                ':nar'    => "Reversal of payable for purchase return #$retNum",
                ':tot'    => $totalAmount,
                ':uid'    => $request->getUserId(),
            ]);
            $voucherId = (int)$pdo->lastInsertId();

            // Line 1: Debit Supplier Payable (reduces supplier payable liability)
            $vlPayStmt = $pdo->prepare("INSERT INTO voucher_lines (voucher_id, account_id, party_type, party_id, debit, credit, description) 
                                        VALUES (:vid, :acc, 'supplier', :sid, :deb, 0, :desc)");
            $vlPayStmt->execute([
                ':vid'  => $voucherId,
                ':acc'  => $payableAccountId,
                ':sid'  => $supplierId,
                ':deb'  => $totalAmount,
                ':desc' => "Payable reversal from purchase return #$retNum",
            ]);

            // Line 2: Credit Inventory Asset (reduces inventory asset)
            $vlInvStmt = $pdo->prepare("INSERT INTO voucher_lines (voucher_id, account_id, party_type, party_id, debit, credit, description) 
                                        VALUES (:vid, :acc, 'none', NULL, 0, :crd, :desc)");
            $vlInvStmt->execute([
                ':vid'  => $voucherId,
                ':acc'  => $inventoryAccountId,
                ':crd'  => $totalAmount,
                ':desc' => "Inventory reduction from purchase return #$retNum",
            ]);

            // Update Supplier current_balance (reduces liability)
            $updSup = $pdo->prepare("UPDATE suppliers SET current_balance = current_balance - :amt, updated_at = NOW() WHERE id = :sid");
            $updSup->execute([':amt' => $totalAmount, ':sid' => $supplierId]);

            // Link Voucher
            $updRtv = $pdo->prepare("UPDATE purchase_returns SET voucher_id = :vid WHERE id = :id");
            $updRtv->execute([':vid' => $voucherId, ':id' => $returnId]);

            Database::commit();
            AuditLogger::log($request, 'PURCHASE_RETURN', 'PurchaseReturn', (string)$returnId, null, [
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
            ], 'Purchase return processed, stock reduced and payable reversed successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to process purchase return: ' . $e->getMessage(), 500);
        }
    }
}
