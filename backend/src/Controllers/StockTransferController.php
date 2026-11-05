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

class StockTransferController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'inventory.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $status = $request->getQuery('status');

        $where = ["st.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(st.transfer_number LIKE :srch OR st.remarks LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($status) {
            $where[] = "st.status = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_transfers st WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT st.*, fw.name as from_warehouse_name, tw.name as to_warehouse_name,
                                          u.name as creator_name
                                   FROM stock_transfers st
                                   JOIN warehouses fw ON st.from_warehouse_id = fw.id
                                   JOIN warehouses tw ON st.to_warehouse_id = tw.id
                                   LEFT JOIN users u ON st.created_by = u.id
                                   WHERE $whereSql
                                   ORDER BY st.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $transfers = $dataStmt->fetchAll();

        Response::success($transfers, 'Stock transfers retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'inventory.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT st.*, fw.name as from_warehouse_name, tw.name as to_warehouse_name,
                                      u.name as creator_name
                               FROM stock_transfers st
                               JOIN warehouses fw ON st.from_warehouse_id = fw.id
                               JOIN warehouses tw ON st.to_warehouse_id = tw.id
                               LEFT JOIN users u ON st.created_by = u.id
                               WHERE st.id = :id AND st.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $transfer = $stmt->fetch();
        if (!$transfer) {
            Response::notFound("Transfer not found");
        }

        $itemsStmt = $pdo->prepare("SELECT sti.*, p.code as product_code, p.name as product_name, u.symbol as unit_symbol
                                    FROM stock_transfer_items sti
                                    JOIN products p ON sti.product_id = p.id
                                    LEFT JOIN units u ON p.unit_id = u.id
                                    WHERE sti.transfer_id = :id");
        $itemsStmt->execute([':id' => $id]);
        $transfer['items'] = $itemsStmt->fetchAll();

        Response::success($transfer);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'inventory.transfer');
        $data = Validator::validate($request->body(), [
            'from_warehouse_id' => 'required|integer',
            'to_warehouse_id'   => 'required|integer',
            'date'              => 'required',
            'items'             => 'required',
        ]);

        if ($data['from_warehouse_id'] == $data['to_warehouse_id']) {
            Response::error("Source and destination warehouses cannot be the same.", 422);
        }

        $items = $request->get('items');
        if (!is_array($items) || empty($items)) {
            Response::error("At least one transfer item is required.", 422);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $companyId = $request->getCompanyId();
            $transferNumber = SequenceHelper::getNextNumber($companyId, 'ST', 'ST');

            $stmt = $pdo->prepare("INSERT INTO stock_transfers (company_id, transfer_number, from_warehouse_id, 
                                                               to_warehouse_id, date, status, remarks, created_by) 
                                   VALUES (:cid, :num, :fwid, :twid, :dt, 'draft', :rem, :uid)");
            $stmt->execute([
                ':cid'  => $companyId,
                ':num'  => $transferNumber,
                ':fwid' => $data['from_warehouse_id'],
                ':twid' => $data['to_warehouse_id'],
                ':dt'   => $data['date'],
                ':rem'  => $request->get('remarks'),
                ':uid'  => $request->getUserId(),
            ]);
            $transferId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO stock_transfer_items (transfer_id, product_id, quantity, unit_cost, total_cost) 
                                       VALUES (:tid, :pid, :qty, :cost, :tot)");

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                if ($qty <= 0) continue;

                // Fetch product cost
                $pStmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = :pid");
                $pStmt->execute([':pid' => $item['product_id']]);
                $cost = (float)$pStmt->fetchColumn();

                $itemStmt->execute([
                    ':tid'  => $transferId,
                    ':pid'  => $item['product_id'],
                    ':qty'  => $qty,
                    ':cost' => $cost,
                    ':tot'  => $qty * $cost,
                ]);
            }

            Database::commit();
            AuditLogger::log($request, 'CREATE', 'StockTransfer', (string)$transferId, null, ['number' => $transferNumber]);
            Response::created(['id' => $transferId, 'transfer_number' => $transferNumber], 'Stock transfer created successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to create stock transfer: ' . $e->getMessage(), 500);
        }
    }

    public function complete(Request $request): void
    {
        PermissionMiddleware::require($request, 'inventory.transfer');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        Database::beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM stock_transfers WHERE id = :id AND company_id = :cid FOR UPDATE");
            $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
            $transfer = $stmt->fetch();

            if (!$transfer) {
                Response::notFound("Transfer not found");
            }
            if ($transfer['status'] === 'completed') {
                Response::error("Transfer is already completed.", 400);
            }
            if ($transfer['status'] === 'cancelled') {
                Response::error("Cannot complete a cancelled transfer.", 400);
            }

            // Fetch items
            $itemsStmt = $pdo->prepare("SELECT * FROM stock_transfer_items WHERE transfer_id = :id");
            $itemsStmt->execute([':id' => $id]);
            $items = $itemsStmt->fetchAll();

            $companyId = (int)$transfer['company_id'];
            $fromWh = (int)$transfer['from_warehouse_id'];
            $toWh = (int)$transfer['to_warehouse_id'];

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (float)$item['quantity'];

                // 1. Check stock in source warehouse
                $sbStmt = $pdo->prepare("SELECT quantity, avg_unit_cost FROM stock_balances 
                                         WHERE company_id = :cid AND warehouse_id = :wid AND product_id = :pid FOR UPDATE");
                $sbStmt->execute([':cid' => $companyId, ':wid' => $fromWh, ':pid' => $productId]);
                $sourceStock = $sbStmt->fetch();

                $currentQty = (float)($sourceStock['quantity'] ?? 0);
                $unitCost = (float)($sourceStock['avg_unit_cost'] ?? $item['unit_cost']);

                if ($currentQty < $qty) {
                    throw new Exception("Insufficient stock for product ID #$productId in source warehouse. Available: $currentQty, Required: $qty");
                }

                // 2. Deduct from source warehouse
                $updSource = $pdo->prepare("UPDATE stock_balances SET quantity = quantity - :qty, updated_at = NOW() 
                                            WHERE company_id = :cid AND warehouse_id = :wid AND product_id = :pid");
                $updSource->execute([':qty' => $qty, ':cid' => $companyId, ':wid' => $fromWh, ':pid' => $productId]);

                // 3. Write TRANSFER_OUT ledger
                $ledgOut = $pdo->prepare("INSERT INTO stock_ledger (company_id, branch_id, warehouse_id, product_id, date, 
                                                                   reference_type, reference_id, qty_in, qty_out, unit_cost, total_cost, remarks) 
                                          VALUES (:cid, :bid, :wid, :pid, :dt, 'TRANSFER_OUT', :ref_id, 0, :qty, :cost, :tot, :rem)");
                $ledgOut->execute([
                    ':cid'    => $companyId,
                    ':bid'    => $request->getBranchId(),
                    ':wid'    => $fromWh,
                    ':pid'    => $productId,
                    ':dt'     => $transfer['date'],
                    ':ref_id' => $id,
                    ':qty'    => $qty,
                    ':cost'   => $unitCost,
                    ':tot'    => $qty * $unitCost,
                    ':rem'    => "Transfer to warehouse #$toWh (" . $transfer['transfer_number'] . ")",
                ]);

                // 4. Add to destination warehouse
                $sbDestStmt = $pdo->prepare("INSERT INTO stock_balances (company_id, warehouse_id, product_id, quantity, avg_unit_cost) 
                                             VALUES (:cid, :wid, :pid, :qty, :cost) 
                                             ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), avg_unit_cost = VALUES(avg_unit_cost), updated_at = NOW()");
                $sbDestStmt->execute([
                    ':cid'  => $companyId,
                    ':wid'  => $toWh,
                    ':pid'  => $productId,
                    ':qty'  => $qty,
                    ':cost' => $unitCost,
                ]);

                // 5. Write TRANSFER_IN ledger
                $ledgIn = $pdo->prepare("INSERT INTO stock_ledger (company_id, branch_id, warehouse_id, product_id, date, 
                                                                  reference_type, reference_id, qty_in, qty_out, unit_cost, total_cost, remarks) 
                                         VALUES (:cid, :bid, :wid, :pid, :dt, 'TRANSFER_IN', :ref_id, :qty, 0, :cost, :tot, :rem)");
                $ledgIn->execute([
                    ':cid'    => $companyId,
                    ':bid'    => $request->getBranchId(),
                    ':wid'    => $toWh,
                    ':pid'    => $productId,
                    ':dt'     => $transfer['date'],
                    ':ref_id' => $id,
                    ':qty'    => $qty,
                    ':cost'   => $unitCost,
                    ':tot'    => $qty * $unitCost,
                    ':rem'    => "Transfer from warehouse #$fromWh (" . $transfer['transfer_number'] . ")",
                ]);
            }

            // Mark transfer completed
            $updTr = $pdo->prepare("UPDATE stock_transfers SET status = 'completed', updated_at = NOW() WHERE id = :id");
            $updTr->execute([':id' => $id]);

            Database::commit();
            AuditLogger::log($request, 'COMPLETE', 'StockTransfer', (string)$id, null, ['status' => 'completed']);
            Response::success(null, 'Stock transfer completed and ledger posted successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to complete transfer: ' . $e->getMessage(), 400);
        }
    }
}
