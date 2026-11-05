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

class SalesOrderController
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
        $status = $request->getQuery('status');
        $customerId = $request->getQuery('customer_id');

        $where = ["so.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(so.so_number LIKE :srch OR c.name LIKE :srch OR so.remarks LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($status) {
            $where[] = "so.status = :status";
            $params[':status'] = $status;
        }

        if ($customerId) {
            $where[] = "so.customer_id = :cust";
            $params[':cust'] = $customerId;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM sales_orders so JOIN customers c ON so.customer_id = c.id WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT so.*, c.name as customer_name, c.code as customer_code,
                                          u.name as creator_name
                                   FROM sales_orders so
                                   JOIN customers c ON so.customer_id = c.id
                                   LEFT JOIN users u ON so.created_by = u.id
                                   WHERE $whereSql
                                   ORDER BY so.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $orders = $dataStmt->fetchAll();

        Response::success($orders, 'Sales orders retrieved', 200, [
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

        $stmt = $pdo->prepare("SELECT so.*, c.name as customer_name, c.code as customer_code, c.email as customer_email,
                                      c.phone as customer_phone, c.address as customer_address,
                                      u.name as creator_name
                               FROM sales_orders so
                               JOIN customers c ON so.customer_id = c.id
                               LEFT JOIN users u ON so.created_by = u.id
                               WHERE so.id = :id AND so.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $so = $stmt->fetch();
        if (!$so) {
            Response::notFound("Sales order not found");
        }

        $itemsStmt = $pdo->prepare("SELECT soi.*, p.code as product_code, p.name as product_name, u.symbol as unit_symbol
                                    FROM sales_order_items soi
                                    JOIN products p ON soi.product_id = p.id
                                    LEFT JOIN units u ON p.unit_id = u.id
                                    WHERE soi.so_id = :id");
        $itemsStmt->execute([':id' => $id]);
        $so['items'] = $itemsStmt->fetchAll();

        Response::success($so);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.create');
        $data = Validator::validate($request->body(), [
            'customer_id' => 'required|integer',
            'date'        => 'required',
            'items'       => 'required',
        ]);

        $items = $request->get('items');
        if (!is_array($items) || empty($items)) {
            Response::error("Sales order must have at least one line item.", 422);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $companyId = $request->getCompanyId();
            $soNum = SequenceHelper::getNextNumber($companyId, 'SO', 'SO');

            $totalAmount = 0.0;
            $taxAmount = 0.0;
            $discountAmount = 0.0;

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $tax = (float)($item['tax_amount'] ?? 0);
                $disc = (float)($item['discount_amount'] ?? 0);

                $totalAmount += ($qty * $price);
                $taxAmount += $tax;
                $discountAmount += $disc;
            }
            $netAmount = $totalAmount + $taxAmount - $discountAmount;

            $stmt = $pdo->prepare("INSERT INTO sales_orders (company_id, branch_id, so_number, customer_id, date, 
                                                           delivery_date, status, total_amount, tax_amount, 
                                                           discount_amount, net_amount, remarks, created_by) 
                                   VALUES (:cid, :bid, :num, :cust, :dt, :ddt, 'draft', :tot, :tax, :disc, :net, :rem, :uid)");
            $stmt->execute([
                ':cid'  => $companyId,
                ':bid'  => $request->getBranchId(),
                ':num'  => $soNum,
                ':cust' => $data['customer_id'],
                ':dt'   => $data['date'],
                ':ddt'  => $request->get('delivery_date'),
                ':tot'  => $totalAmount,
                ':tax'  => $taxAmount,
                ':disc' => $discountAmount,
                ':net'  => $netAmount,
                ':rem'  => $request->get('remarks'),
                ':uid'  => $request->getUserId(),
            ]);
            $soId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO sales_order_items (so_id, product_id, quantity, delivered_quantity, 
                                                                    unit_price, tax_rate, tax_amount, discount_amount, total_price) 
                                       VALUES (:soid, :pid, :qty, 0, :price, :trate, :tax, :disc, :tot)");

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? 0);
                $tax = (float)($item['tax_amount'] ?? 0);
                $disc = (float)($item['discount_amount'] ?? 0);
                $lineTotal = ($qty * $price) + $tax - $disc;

                $itemStmt->execute([
                    ':soid'  => $soId,
                    ':pid'   => $item['product_id'],
                    ':qty'   => $qty,
                    ':price' => $price,
                    ':trate' => $taxRate,
                    ':tax'   => $tax,
                    ':disc'  => $disc,
                    ':tot'   => $lineTotal,
                ]);
            }

            Database::commit();
            AuditLogger::log($request, 'CREATE', 'SalesOrder', (string)$soId, null, ['number' => $soNum]);
            Response::created(['id' => $soId, 'so_number' => $soNum], 'Sales order created successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to create sales order: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.create');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM sales_orders WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $status = $stmt->fetchColumn();

        if ($status !== 'draft') {
            Response::error("Cannot edit sales order with status '$status'.", 400);
        }

        $items = $request->get('items');
        if (!is_array($items) || empty($items)) {
            Response::error("Sales order must have line items.", 422);
        }

        Database::beginTransaction();
        try {
            $totalAmount = 0.0;
            $taxAmount = 0.0;
            $discountAmount = 0.0;

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $tax = (float)($item['tax_amount'] ?? 0);
                $disc = (float)($item['discount_amount'] ?? 0);

                $totalAmount += ($qty * $price);
                $taxAmount += $tax;
                $discountAmount += $disc;
            }
            $netAmount = $totalAmount + $taxAmount - $discountAmount;

            $upd = $pdo->prepare("UPDATE sales_orders SET customer_id = :cust, date = :dt, delivery_date = :ddt, 
                                 total_amount = :tot, tax_amount = :tax, discount_amount = :disc, net_amount = :net, 
                                 remarks = :rem, updated_at = NOW() WHERE id = :id");
            $upd->execute([
                ':cust' => $request->get('customer_id'),
                ':dt'   => $request->get('date'),
                ':ddt'  => $request->get('delivery_date'),
                ':tot'  => $totalAmount,
                ':tax'  => $taxAmount,
                ':disc' => $discountAmount,
                ':net'  => $netAmount,
                ':rem'  => $request->get('remarks'),
                ':id'   => $id,
            ]);

            $del = $pdo->prepare("DELETE FROM sales_order_items WHERE so_id = :id");
            $del->execute([':id' => $id]);

            $itemStmt = $pdo->prepare("INSERT INTO sales_order_items (so_id, product_id, quantity, delivered_quantity, 
                                                                    unit_price, tax_rate, tax_amount, discount_amount, total_price) 
                                       VALUES (:soid, :pid, :qty, 0, :price, :trate, :tax, :disc, :tot)");
            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? 0);
                $tax = (float)($item['tax_amount'] ?? 0);
                $disc = (float)($item['discount_amount'] ?? 0);
                $lineTotal = ($qty * $price) + $tax - $disc;

                $itemStmt->execute([
                    ':soid'  => $id,
                    ':pid'   => $item['product_id'],
                    ':qty'   => $qty,
                    ':price' => $price,
                    ':trate' => $taxRate,
                    ':tax'   => $tax,
                    ':disc'  => $disc,
                    ':tot'   => $lineTotal,
                ]);
            }

            Database::commit();
            AuditLogger::log($request, 'UPDATE', 'SalesOrder', (string)$id);
            Response::success(null, 'Sales order updated successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to update sales order: ' . $e->getMessage(), 500);
        }
    }

    public function confirm(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.approve');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE sales_orders SET status = 'confirmed', updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid AND status = 'draft'");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        if ($stmt->rowCount() === 0) {
            Response::error("Sales order cannot be confirmed.", 400);
        }

        AuditLogger::log($request, 'CONFIRM', 'SalesOrder', (string)$id, null, ['status' => 'confirmed']);
        Response::success(null, 'Sales order confirmed successfully');
    }

    public function cancel(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.approve');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE sales_orders SET status = 'cancelled', updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid AND status IN ('draft', 'confirmed')");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'CANCEL', 'SalesOrder', (string)$id);
        Response::success(null, 'Sales order cancelled');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.create');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM sales_orders WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $status = $stmt->fetchColumn();

        if ($status !== 'draft') {
            Response::error("Only draft sales orders can be deleted.", 400);
        }

        $del = $pdo->prepare("DELETE FROM sales_orders WHERE id = :id");
        $del->execute([':id' => $id]);

        AuditLogger::log($request, 'DELETE', 'SalesOrder', (string)$id);
        Response::success(null, 'Sales order deleted successfully');
    }
}
