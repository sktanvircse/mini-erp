<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\PermissionMiddleware;
use PDO;

class StockController
{
    public function balances(Request $request): void
    {
        PermissionMiddleware::require($request, 'inventory.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $warehouseId = $request->getQuery('warehouse_id');
        $categoryId = $request->getQuery('category_id');
        $search = trim((string)$request->getQuery('search', ''));

        $where = ["p.company_id = :cid", "p.deleted_at IS NULL"];
        $params = [':cid' => $companyId];

        if ($warehouseId) {
            $where[] = "sb.warehouse_id = :wid";
            $params[':wid'] = $warehouseId;
        }

        if ($categoryId) {
            $where[] = "p.category_id = :cat";
            $params[':cat'] = $categoryId;
        }

        if ($search !== '') {
            $where[] = "(p.code LIKE :srch OR p.name LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT sb.*, p.code as product_code, p.name as product_name, p.reorder_level,
                       c.name as category_name, u.symbol as unit_symbol, w.name as warehouse_name,
                       (sb.quantity * sb.avg_unit_cost) as total_value
                FROM stock_balances sb
                JOIN products p ON sb.product_id = p.id
                JOIN warehouses w ON sb.warehouse_id = w.id
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN units u ON p.unit_id = u.id
                WHERE $whereSql
                ORDER BY p.name ASC, w.name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $balances = $stmt->fetchAll();

        Response::success($balances);
    }

    public function ledger(Request $request): void
    {
        PermissionMiddleware::require($request, 'inventory.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 25)));
        $offset = ($page - 1) * $perPage;

        $productId = $request->getQuery('product_id');
        $warehouseId = $request->getQuery('warehouse_id');
        $startDate = $request->getQuery('start_date');
        $endDate = $request->getQuery('end_date');
        $refType = $request->getQuery('reference_type');

        $where = ["sl.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($productId) {
            $where[] = "sl.product_id = :pid";
            $params[':pid'] = $productId;
        }

        if ($warehouseId) {
            $where[] = "sl.warehouse_id = :wid";
            $params[':wid'] = $warehouseId;
        }

        if ($refType) {
            $where[] = "sl.reference_type = :ref_type";
            $params[':ref_type'] = $refType;
        }

        if ($startDate) {
            $where[] = "sl.date >= :sdate";
            $params[':sdate'] = $startDate;
        }

        if ($endDate) {
            $where[] = "sl.date <= :edate";
            $params[':edate'] = $endDate;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_ledger sl WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataSql = "SELECT sl.*, p.code as product_code, p.name as product_name, u.symbol as unit_symbol,
                           w.name as warehouse_name
                    FROM stock_ledger sl
                    JOIN products p ON sl.product_id = p.id
                    JOIN warehouses w ON sl.warehouse_id = w.id
                    LEFT JOIN units u ON p.unit_id = u.id
                    WHERE $whereSql
                    ORDER BY sl.date DESC, sl.id DESC
                    LIMIT $perPage OFFSET $offset";
        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($params);
        $ledger = $dataStmt->fetchAll();

        Response::success($ledger, 'Stock ledger retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function lowStock(Request $request): void
    {
        PermissionMiddleware::require($request, 'inventory.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $sql = "SELECT p.id, p.code, p.name, p.reorder_level, c.name as category_name, u.symbol as unit_symbol,
                       COALESCE(SUM(sb.quantity), 0) as current_stock,
                       (p.reorder_level - COALESCE(SUM(sb.quantity), 0)) as deficit_qty
                FROM products p
                LEFT JOIN stock_balances sb ON p.id = sb.product_id AND sb.company_id = :cid
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN units u ON p.unit_id = u.id
                WHERE p.company_id = :cid2 AND p.deleted_at IS NULL AND p.is_active = 1
                GROUP BY p.id
                HAVING current_stock <= p.reorder_level
                ORDER BY deficit_qty DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cid' => $companyId, ':cid2' => $companyId]);
        $rows = $stmt->fetchAll();

        Response::success($rows);
    }

    public function productMovements(Request $request): void
    {
        PermissionMiddleware::require($request, 'inventory.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $startDate = $request->getQuery('start_date', date('Y-m-01'));
        $endDate = $request->getQuery('end_date', date('Y-m-d'));

        $sql = "SELECT p.id as product_id, p.code as product_code, p.name as product_name, u.symbol as unit_symbol,
                       COALESCE(SUM(sl.qty_in), 0) as total_qty_in,
                       COALESCE(SUM(sl.qty_out), 0) as total_qty_out,
                       (COALESCE(SUM(sl.qty_in), 0) - COALESCE(SUM(sl.qty_out), 0)) as net_movement,
                       COALESCE(SUM(sl.total_cost), 0) as total_movement_value
                FROM products p
                LEFT JOIN stock_ledger sl ON p.id = sl.product_id AND sl.company_id = :cid AND sl.date BETWEEN :sdate AND :edate
                LEFT JOIN units u ON p.unit_id = u.id
                WHERE p.company_id = :cid2 AND p.deleted_at IS NULL
                GROUP BY p.id
                ORDER BY total_qty_out DESC, total_qty_in DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cid'   => $companyId,
            ':sdate' => $startDate,
            ':edate' => $endDate,
            ':cid2'  => $companyId,
        ]);
        Response::success($stmt->fetchAll());
    }
}
