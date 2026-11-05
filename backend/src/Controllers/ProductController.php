<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;
use PDO;

class ProductController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'master.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $categoryId = $request->getQuery('category_id');
        $status = $request->getQuery('is_active');

        $where = ["p.company_id = :cid", "p.deleted_at IS NULL"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(p.code LIKE :srch OR p.name LIKE :srch OR p.description LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($categoryId) {
            $where[] = "p.category_id = :cat";
            $params[':cat'] = $categoryId;
        }

        if ($status !== null && $status !== '') {
            $where[] = "p.is_active = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT p.*, p.cost_price as purchase_price, c.name as category_name, u.name as unit_name, u.symbol as unit_symbol,
                                          COALESCE((SELECT SUM(sb.quantity) FROM stock_balances sb WHERE sb.product_id = p.id AND sb.company_id = :cid2), 0) as total_stock
                                   FROM products p
                                   LEFT JOIN categories c ON p.category_id = c.id
                                   LEFT JOIN units u ON p.unit_id = u.id
                                   WHERE $whereSql
                                   ORDER BY p.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $params[':cid2'] = $companyId;
        $dataStmt->execute($params);
        $products = $dataStmt->fetchAll();

        Response::success($products, 'Products retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'master.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT p.*, p.cost_price as purchase_price, c.name as category_name, u.name as unit_name, u.symbol as unit_symbol
                               FROM products p
                               LEFT JOIN categories c ON p.category_id = c.id
                               LEFT JOIN units u ON p.unit_id = u.id
                               WHERE p.id = :id AND p.company_id = :cid AND p.deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $product = $stmt->fetch();
        if (!$product) {
            Response::notFound("Product not found");
        }

        // Get stock per warehouse
        $whStmt = $pdo->prepare("SELECT sb.warehouse_id, sb.quantity, sb.reserved_qty, sb.avg_unit_cost, w.name as warehouse_name 
                                 FROM stock_balances sb
                                 JOIN warehouses w ON sb.warehouse_id = w.id
                                 WHERE sb.product_id = :id AND sb.company_id = :cid");
        $whStmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $product['stock_by_warehouse'] = $whStmt->fetchAll();

        Response::success($product);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'products.manage');
        $body = $request->body();
        if (!isset($body['cost_price']) && isset($body['purchase_price'])) {
            $body['cost_price'] = $body['purchase_price'];
        }
        $data = Validator::validate($body, [
            'code'          => 'required',
            'name'          => 'required',
            'category_id'   => 'required|integer',
            'unit_id'       => 'required|integer',
            'cost_price'    => 'required|numeric',
            'sale_price'    => 'required|numeric',
        ]);

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            // Check unique code within company
            $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE company_id = :cid AND code = :code AND deleted_at IS NULL");
            $chk->execute([':cid' => $request->getCompanyId(), ':code' => $data['code']]);
            if ((int)$chk->fetchColumn() > 0) {
                Response::error("Product code '{$data['code']}' already exists.", 422);
            }

            $stmt = $pdo->prepare("INSERT INTO products (company_id, code, name, category_id, unit_id, cost_price, 
                                                        sale_price, reorder_level, current_stock, description, is_active) 
                                   VALUES (:cid, :code, :name, :cat, :unit, :cost, :sale, :reorder, 0, :desc, :act)");
            $stmt->execute([
                ':cid'     => $request->getCompanyId(),
                ':code'    => $data['code'],
                ':name'    => $data['name'],
                ':cat'     => $data['category_id'],
                ':unit'    => $data['unit_id'],
                ':cost'    => $data['cost_price'],
                ':sale'    => $data['sale_price'],
                ':reorder' => (float)$request->get('reorder_level', 0),
                ':desc'    => $request->get('description'),
                ':act'     => (int)$request->get('is_active', 1),
            ]);
            $productId = (int)$pdo->lastInsertId();

            // Optional opening stock
            $openingStock = (float)$request->get('opening_stock', 0);
            $warehouseId = $request->get('warehouse_id');
            if ($openingStock > 0 && $warehouseId) {
                $sbStmt = $pdo->prepare("INSERT INTO stock_balances (company_id, warehouse_id, product_id, quantity, avg_unit_cost) 
                                         VALUES (:cid, :wid, :pid, :qty, :cost)");
                $sbStmt->execute([
                    ':cid'  => $request->getCompanyId(),
                    ':wid'  => $warehouseId,
                    ':pid'  => $productId,
                    ':qty'  => $openingStock,
                    ':cost' => $data['cost_price'],
                ]);

                $slStmt = $pdo->prepare("INSERT INTO stock_ledger (company_id, branch_id, warehouse_id, product_id, date, 
                                                                  reference_type, reference_id, qty_in, qty_out, unit_cost, total_cost, remarks) 
                                         VALUES (:cid, :bid, :wid, :pid, CURRENT_DATE(), 'ADJUSTMENT', :pid2, :qin, 0, :cost, :tot, 'Opening balance')");
                $slStmt->execute([
                    ':cid'  => $request->getCompanyId(),
                    ':bid'  => $request->getBranchId(),
                    ':wid'  => $warehouseId,
                    ':pid'  => $productId,
                    ':pid2' => $productId,
                    ':qin'  => $openingStock,
                    ':cost' => $data['cost_price'],
                    ':tot'  => $openingStock * (float)$data['cost_price'],
                ]);

                // Update product current_stock
                $updPrd = $pdo->prepare("UPDATE products SET current_stock = :qty WHERE id = :pid");
                $updPrd->execute([':qty' => $openingStock, ':pid' => $productId]);
            }

            Database::commit();
            AuditLogger::log($request, 'CREATE', 'Product', (string)$productId, null, $data);
            Response::created(['id' => $productId], 'Product created successfully');
        } catch (\Exception $e) {
            Database::rollBack();
            Response::error('Failed to create product: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'products.manage');
        $id = (int)$request->getParam('id');
        $body = $request->body();
        if (!isset($body['cost_price']) && isset($body['purchase_price'])) {
            $body['cost_price'] = $body['purchase_price'];
        }
        $data = Validator::validate($body, [
            'code'        => 'required',
            'name'        => 'required',
            'category_id' => 'required|integer',
            'unit_id'     => 'required|integer',
            'cost_price'  => 'required|numeric',
            'sale_price'  => 'required|numeric',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE products SET code = :code, name = :name, category_id = :cat, unit_id = :unit, 
                               cost_price = :cost, sale_price = :sale, reorder_level = :reorder, description = :desc, 
                               is_active = :act, updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid");
        $stmt->execute([
            ':code'    => $data['code'],
            ':name'    => $data['name'],
            ':cat'     => $data['category_id'],
            ':unit'    => $data['unit_id'],
            ':cost'    => $data['cost_price'],
            ':sale'    => $data['sale_price'],
            ':reorder' => (float)$request->get('reorder_level', 0),
            ':desc'    => $request->get('description'),
            ':act'     => (int)$request->get('is_active', 1),
            ':id'      => $id,
            ':cid'     => $request->getCompanyId(),
        ]);

        AuditLogger::log($request, 'UPDATE', 'Product', (string)$id, null, $data);
        Response::success(null, 'Product updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'products.manage');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE products SET deleted_at = NOW(), is_active = 0 WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'Product', (string)$id);
        Response::success(null, 'Product deleted successfully');
    }
}
