<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\SequenceHelper;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;
use Exception;
use PDO;

class DeliveryController
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
        $warehouseId = $request->getQuery('warehouse_id');

        $where = ["d.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(d.delivery_number LIKE :srch OR c.name LIKE :srch OR d.remarks LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($customerId) {
            $where[] = "d.customer_id = :cust";
            $params[':cust'] = $customerId;
        }

        if ($warehouseId) {
            $where[] = "d.warehouse_id = :wh";
            $params[':wh'] = $warehouseId;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM deliveries d JOIN customers c ON d.customer_id = c.id WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT d.*, c.name as customer_name, c.code as customer_code,
                                          w.name as warehouse_name, so.so_number, u.name as creator_name
                                   FROM deliveries d
                                   JOIN customers c ON d.customer_id = c.id
                                   JOIN warehouses w ON d.warehouse_id = w.id
                                   LEFT JOIN sales_orders so ON d.so_id = so.id
                                   LEFT JOIN users u ON d.created_by = u.id
                                   WHERE $whereSql
                                   ORDER BY d.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $deliveries = $dataStmt->fetchAll();

        Response::success($deliveries, 'Deliveries retrieved', 200, [
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

        $stmt = $pdo->prepare("SELECT d.*, c.name as customer_name, c.code as customer_code,
                                      w.name as warehouse_name, so.so_number, u.name as creator_name
                               FROM deliveries d
                               JOIN customers c ON d.customer_id = c.id
                               JOIN warehouses w ON d.warehouse_id = w.id
                               LEFT JOIN sales_orders so ON d.so_id = so.id
                               LEFT JOIN users u ON d.created_by = u.id
                               WHERE d.id = :id AND d.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $delivery = $stmt->fetch();
        if (!$delivery) {
            Response::notFound("Delivery note not found");
        }

        $itemsStmt = $pdo->prepare("SELECT di.*, p.code as product_code, p.name as product_name, u.symbol as unit_symbol
                                    FROM delivery_items di
                                    JOIN products p ON di.product_id = p.id
                                    LEFT JOIN units u ON p.unit_id = u.id
                                    WHERE di.delivery_id = :id");
        $itemsStmt->execute([':id' => $id]);
        $delivery['items'] = $itemsStmt->fetchAll();

        Response::success($delivery);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.deliver');
        $data = Validator::validate($request->body(), [
            'customer_id'  => 'required|integer',
            'warehouse_id' => 'required|integer',
            'date'         => 'required',
            'items'        => 'required',
        ]);

        $items = $request->get('items');
        if (!is_array($items) || empty($items)) {
            Response::error("Delivery note must include at least one item.", 422);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $companyId = $request->getCompanyId();
            $branchId = $request->getBranchId();
            $soId = $request->get('so_id') ? (int)$request->get('so_id') : null;
            $customerId = (int)$data['customer_id'];
            $warehouseId = (int)$data['warehouse_id'];
            $deliveryDate = $data['date'];
            $allowNegativeStock = (int)Env::get('ALLOW_NEGATIVE_STOCK', 0);

            $delNum = SequenceHelper::getNextNumber($companyId, 'DEL', 'DEL');

            $delStmt = $pdo->prepare("INSERT INTO deliveries (company_id, branch_id, delivery_number, so_id, 
                                                            customer_id, warehouse_id, date, status, remarks, created_by) 
                                      VALUES (:cid, :bid, :num, :soid, :cust, :wid, :dt, 'delivered', :rem, :uid)");
            $delStmt->execute([
                ':cid'  => $companyId,
                ':bid'  => $branchId,
                ':num'  => $delNum,
                ':soid' => $soId,
                ':cust' => $customerId,
                ':wid'  => $warehouseId,
                ':dt'   => $deliveryDate,
                ':rem'  => $request->get('remarks'),
                ':uid'  => $request->getUserId(),
            ]);
            $deliveryId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO delivery_items (delivery_id, so_item_id, product_id, quantity, unit_price, total_price) 
                                       VALUES (:delid, :soi, :pid, :qty, :price, :tot)");

            $slStmt = $pdo->prepare("INSERT INTO stock_ledger (company_id, branch_id, warehouse_id, product_id, date, 
                                                             reference_type, reference_id, qty_in, qty_out, unit_cost, total_cost, remarks) 
                                     VALUES (:cid, :bid, :wid, :pid, :dt, 'DELIVERY', :ref_id, 0, :qout, :cost, :tot, :rem)");

            $updProdStock = $pdo->prepare("UPDATE products SET current_stock = current_stock - :qty WHERE id = :pid");

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $qty = (float)$item['quantity'];
                $price = (float)($item['unit_price'] ?? 0);
                $soItemId = !empty($item['so_item_id']) ? (int)$item['so_item_id'] : null;

                if ($qty <= 0) continue;

                // 1. Check available stock in warehouse
                $sbStmt = $pdo->prepare("SELECT quantity, avg_unit_cost FROM stock_balances 
                                         WHERE company_id = :cid AND warehouse_id = :wid AND product_id = :pid FOR UPDATE");
                $sbStmt->execute([':cid' => $companyId, ':wid' => $warehouseId, ':pid' => $productId]);
                $sbRow = $sbStmt->fetch();

                $currentQty = (float)($sbRow['quantity'] ?? 0);
                $avgUnitCost = (float)($sbRow['avg_unit_cost'] ?? 0);

                if (!$allowNegativeStock && $currentQty < $qty) {
                    throw new Exception("Insufficient stock for product ID #$productId in selected warehouse. Available: $currentQty, Requested: $qty");
                }

                // 2. Deduct stock balance
                $updSb = $pdo->prepare("UPDATE stock_balances SET quantity = quantity - :qty, updated_at = NOW() 
                                        WHERE company_id = :cid AND warehouse_id = :wid AND product_id = :pid");
                $updSb->execute([':qty' => $qty, ':cid' => $companyId, ':wid' => $warehouseId, ':pid' => $productId]);

                // 3. Deduct product current_stock
                $updProdStock->execute([':qty' => $qty, ':pid' => $productId]);

                // 4. Insert delivery item
                $lineTotal = $qty * $price;
                $itemStmt->execute([
                    ':delid' => $deliveryId,
                    ':soi'   => $soItemId,
                    ':pid'   => $productId,
                    ':qty'   => $qty,
                    ':price' => $price,
                    ':tot'   => $lineTotal,
                ]);

                // 5. Insert Stock Ledger entry
                $slStmt->execute([
                    ':cid'    => $companyId,
                    ':bid'    => $branchId,
                    ':wid'    => $warehouseId,
                    ':pid'    => $productId,
                    ':dt'     => $deliveryDate,
                    ':ref_id' => $deliveryId,
                    ':qout'   => $qty,
                    ':cost'   => $avgUnitCost,
                    ':tot'    => $qty * $avgUnitCost,
                    ':rem'    => "Delivery #$delNum to customer #$customerId",
                ]);

                // 6. Update Sales Order item delivered_quantity if linked
                if ($soItemId) {
                    $updSoi = $pdo->prepare("UPDATE sales_order_items SET delivered_quantity = delivered_quantity + :qty WHERE id = :soi");
                    $updSoi->execute([':qty' => $qty, ':soi' => $soItemId]);
                }
            }

            // 7. Update Sales Order status if linked
            if ($soId) {
                $soCheck = $pdo->prepare("SELECT SUM(quantity) as total_ordered, SUM(delivered_quantity) as total_delivered 
                                         FROM sales_order_items WHERE so_id = :soid");
                $soCheck->execute([':soid' => $soId]);
                $soTotals = $soCheck->fetch();

                $totalOrdered = (float)($soTotals['total_ordered'] ?? 0);
                $totalDelivered = (float)($soTotals['total_delivered'] ?? 0);

                $newSoStatus = ($totalDelivered >= $totalOrdered) ? 'delivered' : 'partially_delivered';
                $updSo = $pdo->prepare("UPDATE sales_orders SET status = :st, updated_at = NOW() WHERE id = :soid");
                $updSo->execute([':st' => $newSoStatus, ':soid' => $soId]);
            }

            Database::commit();
            AuditLogger::log($request, 'DELIVER', 'Delivery', (string)$deliveryId, null, [
                'delivery_number' => $delNum,
                'so_id'           => $soId,
            ]);

            Response::created([
                'id'              => $deliveryId,
                'delivery_number' => $delNum,
            ], 'Goods delivered and stock deducted successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to create delivery: ' . $e->getMessage(), 400);
        }
    }
}
