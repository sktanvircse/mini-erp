<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;

class UnitController
{
    public function index(Request $request): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM units WHERE company_id = :cid ORDER BY name ASC");
        $stmt->execute([':cid' => $request->getCompanyId()]);
        Response::success($stmt->fetchAll());
    }

    public function show(Request $request): void
    {
        $id = $request->getParam('id');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM units WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $unit = $stmt->fetch();
        if (!$unit) {
            Response::notFound("Unit not found");
        }
        Response::success($unit);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'master.view');
        $data = Validator::validate($request->body(), [
            'name'   => 'required',
            'symbol' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO units (company_id, name, symbol, is_active) VALUES (:cid, :name, :sym, :act)");
        $stmt->execute([
            ':cid'  => $request->getCompanyId(),
            ':name' => $data['name'],
            ':sym'  => $data['symbol'],
            ':act'  => (int)$request->get('is_active', 1),
        ]);
        $newId = (int)$pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE', 'Unit', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'Unit created successfully');
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'master.view');
        $id = (int)$request->getParam('id');
        $data = Validator::validate($request->body(), [
            'name'   => 'required',
            'symbol' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE units SET name = :name, symbol = :sym, is_active = :act, updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid");
        $stmt->execute([
            ':name' => $data['name'],
            ':sym'  => $data['symbol'],
            ':act'  => (int)$request->get('is_active', 1),
            ':id'   => $id,
            ':cid'  => $request->getCompanyId(),
        ]);

        AuditLogger::log($request, 'UPDATE', 'Unit', (string)$id, null, $data);
        Response::success(null, 'Unit updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'master.view');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE unit_id = :id AND deleted_at IS NULL");
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() > 0) {
            Response::error('Cannot delete unit assigned to active products.', 400);
        }

        $stmt = $pdo->prepare("DELETE FROM units WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'Unit', (string)$id);
        Response::success(null, 'Unit deleted successfully');
    }
}
