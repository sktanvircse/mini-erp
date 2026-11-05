<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;

class BranchController
{
    public function index(Request $request): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM branches WHERE company_id = :cid ORDER BY id ASC");
        $stmt->execute([':cid' => $request->getCompanyId()]);
        Response::success($stmt->fetchAll());
    }

    public function show(Request $request): void
    {
        $id = $request->getParam('id');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM branches WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $branch = $stmt->fetch();
        if (!$branch) {
            Response::notFound("Branch not found");
        }
        Response::success($branch);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'settings.manage');
        $data = Validator::validate($request->body(), [
            'code' => 'required',
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO branches (company_id, code, name, email, phone, address, is_active) 
                               VALUES (:cid, :code, :name, :email, :phone, :address, :act)");
        $stmt->execute([
            ':cid'     => $request->getCompanyId(),
            ':code'    => $data['code'],
            ':name'    => $data['name'],
            ':email'   => $request->get('email'),
            ':phone'   => $request->get('phone'),
            ':address' => $request->get('address'),
            ':act'     => (int)($request->get('is_active', 1)),
        ]);
        $newId = $pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE', 'Branch', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'Branch created successfully');
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'settings.manage');
        $id = $request->getParam('id');
        $data = Validator::validate($request->body(), [
            'code' => 'required',
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE branches SET code = :code, name = :name, email = :email, phone = :phone, 
                               address = :address, is_active = :act, updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid");
        $stmt->execute([
            ':code'    => $data['code'],
            ':name'    => $data['name'],
            ':email'   => $request->get('email'),
            ':phone'   => $request->get('phone'),
            ':address' => $request->get('address'),
            ':act'     => (int)($request->get('is_active', 1)),
            ':id'      => $id,
            ':cid'     => $request->getCompanyId(),
        ]);

        AuditLogger::log($request, 'UPDATE', 'Branch', (string)$id, null, $data);
        Response::success(null, 'Branch updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'settings.manage');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        // Check if branches have users
        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE branch_id = :id");
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() > 0) {
            Response::error('Cannot delete branch with assigned users.', 400);
        }

        $stmt = $pdo->prepare("DELETE FROM branches WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'Branch', (string)$id);
        Response::success(null, 'Branch deleted');
    }
}
