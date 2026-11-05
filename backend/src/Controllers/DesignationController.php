<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;

class DesignationController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.view');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT d.*, COUNT(e.id) as employee_count 
                               FROM designations d 
                               LEFT JOIN employees e ON d.id = e.designation_id AND e.deleted_at IS NULL
                               WHERE d.company_id = :cid 
                               GROUP BY d.id ORDER BY d.name ASC");
        $stmt->execute([':cid' => $request->getCompanyId()]);
        Response::success($stmt->fetchAll());
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.manage');
        $data = Validator::validate($request->body(), [
            'code' => 'required',
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO designations (company_id, code, name, is_active) VALUES (:cid, :code, :name, :act)");
        $stmt->execute([
            ':cid'  => $request->getCompanyId(),
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':act'  => (int)$request->get('is_active', 1),
        ]);
        $newId = (int)$pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE', 'Designation', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'Designation created successfully');
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.manage');
        $id = (int)$request->getParam('id');
        $data = Validator::validate($request->body(), [
            'code' => 'required',
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE designations SET code = :code, name = :name, is_active = :act, updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid");
        $stmt->execute([
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':act'  => (int)$request->get('is_active', 1),
            ':id'   => $id,
            ':cid'  => $request->getCompanyId(),
        ]);

        AuditLogger::log($request, 'UPDATE', 'Designation', (string)$id, null, $data);
        Response::success(null, 'Designation updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.manage');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $chk = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE designation_id = :id AND deleted_at IS NULL");
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() > 0) {
            Response::error('Cannot delete designation with active employees.', 400);
        }

        $stmt = $pdo->prepare("DELETE FROM designations WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'Designation', (string)$id);
        Response::success(null, 'Designation deleted successfully');
    }
}
