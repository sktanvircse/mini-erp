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

class GoodsReceiptController
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
        $warehouseId = $request->getQuery('warehouse_id');

        $where = ["grn.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(grn.grn_number LIKE :srch OR s.name LIKE :srch OR grn.remarks LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($supplierId) {
            $where[] = "grn.supplier_id = :sup";
            $params[':sup'] = $supplierId;
        }

        if ($warehouseId) {
            $where[] = "grn.warehouse_id = :wh";
            $params[':wh'] = $warehouseId;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM goods_receipts grn JOIN suppliers s ON grn.supplier_id = s.id WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT grn.*, s.name as supplier_name, s.code as supplier_code,
                                          w.name as warehouse_name, po.po_number, vr.voucher_number
                                   FROM goods_receipts grn
                                   JOIN suppliers s ON grn.supplier_id = s.id
                                   JOIN warehouses w ON grn.warehouse_id = w.id
                                   LEFT JOIN purchase_orders po ON grn.po_id = po.id
                                   LEFT JOIN vouchers vr ON grn.voucher_id = vr.id
                                   WHERE $whereSql
                                   ORDER BY grn.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $grns = $dataStmt->fetchAll();

        Response::success($grns, 'Goods receipts retrieved', 200, [
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

        $stmt = $pdo->prepare("SELECT grn.*, s.name as supplier_name, s.code as supplier_code,
                                      w.name as warehouse_name, po.po_number, vr.voucher_number
                               FROM goods_receipts grn
                               JOIN suppliers s ON grn.supplier_id = s.id
                               JOIN warehouses w ON grn.warehouse_id = w.id
                               LEFT JOIN purchase_orders po ON grn.po_id = po.id
                               LEFT JOIN vouchers vr ON grn.voucher_id = vr.id
                               WHERE grn.id = :id AND grn.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $grn = $stmt->fetch();
        if (!$grn) {
            Response::notFound("Goods receipt not found");
        }

        $itemsStmt = $pdo->prepare("SELECT gri.*, p.code as product_code, p.name as product_name, u.symbol as unit_symbol
                                    FROM goods_receipt_items gri
                                    JOIN products p ON gri.product_id = p.id
                                    LEFT JOIN units u ON p.unit_id = u.id
                                    WHERE gri.grn_id = :id");
        $itemsStmt->execute([':id' => $id]);
        $grn['items'] = $itemsStmt->fetchAll();

        Response::success($grn);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.grn');
        $data = Validator::validate($request->body(), [
            'supplier_id'  => 'required|integer',
            'warehouse_id' => 'required|integer',
            'date'         => 'required',
            'items'        => 'required',
        ]);

        $items = $request->get('items');
        if (!is_array($items) || empty($items)) {
            Response::error("GRN must include at least one item.", 422);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $companyId = $request->getCompanyId();
            $branchId = $request->getBranchId();
            $poId = $request->get('po_id') ? (int)$request->get('po_id') : null;
            $supplierId = (int)$data['supplier_id'];
            $warehouseId = (int)$data['warehouse_id'];
            $grnDate = $data['date'];

            // 1. Calculate total GRN amount
            $totalAmount = 0.0;
            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $totalAmount += ($qty * $price);
            }

            if ($totalAmount <= 0) {
                throw new Exception("GRN total amount must be greater than zero.");
            }

            // 2. Generate GRN document number
            $grnNum = SequenceHelper::getNextNumber($companyId, 'GRN', 'GRN');

            // 3. Insert Goods Receipt
            $grnStmt = $pdo->prepare("INSERT INTO goods_receipts (company_id, branch_id, grn_number, po_id, supplier_id, 
                                                                warehouse_id, date, status, total_amount, remarks, received_by) 
                                      VALUES (:cid, :bid, :num, :poid, :sid, :wid, :dt, 'approved', :tot, :rem, :uid)");
            $grnStmt->execute([
                ':cid'  => $companyId,
                ':bid'  => $branchId,
                ':num'  => $grnNum,
                ':poid' => $poId,
                ':sid'  => $supplierId,
                ':wid'  => $warehouseId,
                ':dt'   => $grnDate,
                ':tot'  => $totalAmount,
                ':rem'  => $request->get('remarks'),
                ':uid'  => $request->getUserId(),
            ]);
            $grnId = (int)$pdo->lastInsertId();

            // 4. Insert Items, Update Stock Balances & Stock Ledger
            $griStmt = $pdo->prepare("INSERT INTO goods_receipt_items (grn_id, po_item_id, product_id, quantity, unit_price, total_price) 
                                      VALUES (:grnid, :poi, :pid, :qty, :price, :tot)");

            $sbStmt = $pdo->prepare("INSERT INTO stock_balances (company_id, warehouse_id, product_id, quantity, avg_unit_cost) 
                                     VALUES (:cid, :wid, :pid, :qty, :cost) 
                                     ON DUPLICATE KEY UPDATE 
                                        avg_unit_cost = ((quantity * avg_unit_cost) + (VALUES(quantity) * VALUES(avg_unit_cost))) / (quantity + VALUES(quantity)),
                                        quantity = quantity + VALUES(quantity),
                                        updated_at = NOW()");

            $slStmt = $pdo->prepare("INSERT INTO stock_ledger (company_id, branch_id, warehouse_id, product_id, date, 
                                                             reference_type, reference_id, qty_in, qty_out, unit_cost, total_cost, remarks) 
                                     VALUES (:cid, :bid, :wid, :pid, :dt, 'GRN', :ref_id, :qin, 0, :cost, :tot, :rem)");

            $updProdStock = $pdo->prepare("UPDATE products SET current_stock = current_stock + :qty WHERE id = :pid");

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (float)$item['quantity'];
                $price = (float)$item['unit_price'];
                $lineTotal = $qty * $price;
                $poItemId = !empty($item['po_item_id']) ? (int)$item['po_item_id'] : null;

                if ($qty <= 0) continue;

                // Insert GRN Item
                $griStmt->execute([
                    ':grnid' => $grnId,
                    ':poi'   => $poItemId,
                    ':pid'   => $productId,
                    ':qty'   => $qty,
                    ':price' => $price,
                    ':tot'   => $lineTotal,
                ]);

                // Update Stock Balance
                $sbStmt->execute([
                    ':cid'  => $companyId,
                    ':wid'  => $warehouseId,
                    ':pid'  => $productId,
                    ':qty'  => $qty,
                    ':cost' => $price,
                ]);

                // Update Overall Product Stock
                $updProdStock->execute([':qty' => $qty, ':pid' => $productId]);

                // Insert Stock Ledger
                $slStmt->execute([
                    ':cid'    => $companyId,
                    ':bid'    => $branchId,
                    ':wid'    => $warehouseId,
                    ':pid'    => $productId,
                    ':dt'     => $grnDate,
                    ':ref_id' => $grnId,
                    ':qin'    => $qty,
                    ':cost'   => $price,
                    ':tot'    => $lineTotal,
                    ':rem'    => "GRN #$grnNum from supplier #$supplierId",
                ]);

                // Update PO Item received quantity if linked
                if ($poItemId) {
                    $updPoi = $pdo->prepare("UPDATE purchase_order_items SET received_quantity = received_quantity + :qty WHERE id = :poi");
                    $updPoi->execute([':qty' => $qty, ':poi' => $poItemId]);
                }
            }

            // 5. Update PO status if linked
            if ($poId) {
                $poItemsCheck = $pdo->prepare("SELECT SUM(quantity) as total_ordered, SUM(received_quantity) as total_received 
                                              FROM purchase_order_items WHERE po_id = :poid");
                $poItemsCheck->execute([':poid' => $poId]);
                $poTotals = $poItemsCheck->fetch();

                $totalOrdered = (float)($poTotals['total_ordered'] ?? 0);
                $totalReceived = (float)($poTotals['total_received'] ?? 0);

                $newPoStatus = ($totalReceived >= $totalOrdered) ? 'received' : 'partially_received';
                $updPo = $pdo->prepare("UPDATE purchase_orders SET status = :st, updated_at = NOW() WHERE id = :poid");
                $updPo->execute([':st' => $newPoStatus, ':poid' => $poId]);
            }

            // 6. Create Double-Entry Accounting Voucher: Debit Inventory Asset, Credit Supplier Payable
            // Accounts: Inventory Asset (code 1040, id 5), Accounts Payable (code 2010, id 9)
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
                                     VALUES (:cid, :bid, :vnum, 'purchase', :dt, 'GRN', :ref_id, :nar, :tot, :tot, 'posted', :uid, :uid, NOW())");
            $vrStmt->execute([
                ':cid'    => $companyId,
                ':bid'    => $branchId,
                ':vnum'   => $vrNum,
                ':dt'     => $grnDate,
                ':ref_id' => $grnId,
                ':nar'    => "Inventory received against GRN #$grnNum",
                ':tot'    => $totalAmount,
                ':uid'    => $request->getUserId(),
            ]);
            $voucherId = (int)$pdo->lastInsertId();

            // Line 1: Debit Inventory
            $vlStmt = $pdo->prepare("INSERT INTO voucher_lines (voucher_id, account_id, party_type, party_id, debit, credit, description) 
                                     VALUES (:vid, :acc, 'none', NULL, :deb, 0, :desc)");
            $vlStmt->execute([
                ':vid'  => $voucherId,
                ':acc'  => $inventoryAccountId,
                ':deb'  => $totalAmount,
                ':desc' => "Inventory addition from GRN #$grnNum",
            ]);

            // Line 2: Credit Supplier Payable
            $vlPayStmt = $pdo->prepare("INSERT INTO voucher_lines (voucher_id, account_id, party_type, party_id, debit, credit, description) 
                                        VALUES (:vid, :acc, 'supplier', :sid, 0, :crd, :desc)");
            $vlPayStmt->execute([
                ':vid'  => $voucherId,
                ':acc'  => $payableAccountId,
                ':sid'  => $supplierId,
                ':crd'  => $totalAmount,
                ':desc' => "Accounts payable liability for GRN #$grnNum",
            ]);

            // 7. Update Supplier current_balance (Increase liability)
            $updSup = $pdo->prepare("UPDATE suppliers SET current_balance = current_balance + :amt, updated_at = NOW() WHERE id = :sid");
            $updSup->execute([':amt' => $totalAmount, ':sid' => $supplierId]);

            // 8. Link Voucher to GRN
            $updGrnV = $pdo->prepare("UPDATE goods_receipts SET voucher_id = :vid WHERE id = :id");
            $updGrnV->execute([':vid' => $voucherId, ':id' => $grnId]);

            Database::commit();
            AuditLogger::log($request, 'CREATE_GRN', 'GoodsReceipt', (string)$grnId, null, [
                'grn_number'     => $grnNum,
                'voucher_number' => $vrNum,
                'total_amount'   => $totalAmount,
            ]);

            Response::created([
                'id'             => $grnId,
                'grn_number'     => $grnNum,
                'voucher_id'     => $voucherId,
                'voucher_number' => $vrNum,
                'total_amount'   => $totalAmount,
            ], 'Goods receipt processed, stock increased and payable voucher posted successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to save Goods Receipt: ' . $e->getMessage(), 500);
        }
    }
}
