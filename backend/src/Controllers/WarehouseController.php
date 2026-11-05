<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;

class WarehouseController
{
    public function index(Request $request): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT w.*, b.name as branch_name 
                               FROM warehouses w 
                               LEFT JOIN branches b ON w.branch_id = b.id
                               WHERE w.company_id = :cid ORDER BY w.code ASC");
        $stmt->execute([':cid' => $request->getCompanyId()]);
        Response::success($stmt->fetchAll());
    }

    public function show(Request $request): void
    {
        $id = $request->getParam('id');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM warehouses WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $wh = $stmt->fetch();
        if (!$wh) {
            Response::notFound("Warehouse not found");
        }
        Response::success($wh);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'warehouses.manage');
        $data = Validator::validate($request->body(), [
            'code' => 'required',
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO warehouses (company_id, branch_id, code, name, location, is_active) 
                               VALUES (:cid, :bid, :code, :name, :loc, :act)");
        $stmt->execute([
            ':cid'  => $request->getCompanyId(),
            ':bid'  => $request->get('branch_id') ?: null,
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':loc'  => $request->get('location'),
            ':act'  => (int)$request->get('is_active', 1),
        ]);
        $newId = (int)$pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE', 'Warehouse', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'Warehouse created successfully');
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'warehouses.manage');
        $id = (int)$request->getParam('id');
        $data = Validator::validate($request->body(), [
            'code' => 'required',
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE warehouses SET code = :code, name = :name, branch_id = :bid, 
                               location = :loc, is_active = :act, updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid");
        $stmt->execute([
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':bid'  => $request->get('branch_id') ?: null,
            ':loc'  => $request->get('location'),
            ':act'  => (int)$request->get('is_active', 1),
            ':id'   => $id,
            ':cid'  => $request->getCompanyId(),
        ]);

        AuditLogger::log($request, 'UPDATE', 'Warehouse', (string)$id, null, $data);
        Response::success(null, 'Warehouse updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'warehouses.manage');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        // Check stock balances
        $chk = $pdo->prepare("SELECT SUM(quantity) FROM stock_balances WHERE warehouse_id = :id AND company_id = :cid");
        $chk->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $qty = (float)$chk->fetchColumn();
        if ($qty > 0) {
            Response::error('Cannot delete warehouse with existing physical stock.', 400);
        }

        $stmt = $pdo->prepare("DELETE FROM warehouses WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'Warehouse', (string)$id);
        Response::success(null, 'Warehouse deleted successfully');
    }
}
