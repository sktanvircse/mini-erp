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

class UserController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'users.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $roleId = $request->getQuery('role_id');
        $status = $request->getQuery('is_active');

        $where = ["u.company_id = :cid", "u.deleted_at IS NULL"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(u.name LIKE :srch OR u.email LIKE :srch OR u.phone LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($roleId !== null && $roleId !== '') {
            $where[] = "u.role_id = :role_id";
            $params[':role_id'] = $roleId;
        }

        if ($status !== null && $status !== '') {
            $where[] = "u.is_active = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT u.id, u.company_id, u.branch_id, u.role_id, u.name, u.email, 
                                          u.phone, u.is_active, u.force_password_change, u.created_at,
                                          r.name as role_name, b.name as branch_name
                                   FROM users u
                                   JOIN roles r ON u.role_id = r.id
                                   LEFT JOIN branches b ON u.branch_id = b.id
                                   WHERE $whereSql
                                   ORDER BY u.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $users = $dataStmt->fetchAll();

        Response::success($users, 'Users retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'users.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT u.id, u.company_id, u.branch_id, u.role_id, u.name, u.email, 
                                      u.phone, u.is_active, u.force_password_change, u.created_at,
                                      r.name as role_name, b.name as branch_name
                               FROM users u
                               JOIN roles r ON u.role_id = r.id
                               LEFT JOIN branches b ON u.branch_id = b.id
                               WHERE u.id = :id AND u.company_id = :cid AND u.deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $user = $stmt->fetch();
        if (!$user) {
            Response::notFound("User not found");
        }
        Response::success($user);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'users.manage');
        $data = Validator::validate($request->body(), [
            'name'     => 'required|min:2',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role_id'  => 'required|integer',
        ]);

        $pdo = Database::getConnection();
        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $branchId = $request->get('branch_id') ?: null;
        $phone = $request->get('phone');
        $isActive = (int)$request->get('is_active', 1);

        $stmt = $pdo->prepare("INSERT INTO users (company_id, branch_id, role_id, name, email, password, phone, is_active, force_password_change) 
                               VALUES (:cid, :bid, :rid, :name, :email, :pwd, :phone, :act, 0)");
        $stmt->execute([
            ':cid'   => $request->getCompanyId(),
            ':bid'   => $branchId,
            ':rid'   => $data['role_id'],
            ':name'  => $data['name'],
            ':email' => $data['email'],
            ':pwd'   => $hash,
            ':phone' => $phone,
            ':act'   => $isActive,
        ]);
        $newId = (int)$pdo->lastInsertId();

        unset($data['password']);
        AuditLogger::log($request, 'CREATE', 'User', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'User created successfully');
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'users.manage');
        $id = (int)$request->getParam('id');
        $data = Validator::validate($request->body(), [
            'name'    => 'required|min:2',
            'email'   => "required|email|unique:users,email,$id,id",
            'role_id' => 'required|integer',
        ]);

        $pdo = Database::getConnection();
        $branchId = $request->get('branch_id') ?: null;
        $phone = $request->get('phone');
        $isActive = (int)$request->get('is_active', 1);

        $sql = "UPDATE users SET name = :name, email = :email, role_id = :rid, branch_id = :bid, 
                       phone = :phone, is_active = :act, updated_at = NOW() WHERE id = :id AND company_id = :cid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name'  => $data['name'],
            ':email' => $data['email'],
            ':rid'   => $data['role_id'],
            ':bid'   => $branchId,
            ':phone' => $phone,
            ':act'   => $isActive,
            ':id'    => $id,
            ':cid'   => $request->getCompanyId(),
        ]);

        // Optional password change
        if (!empty($request->get('password'))) {
            $newHash = password_hash($request->get('password'), PASSWORD_DEFAULT);
            $pStmt = $pdo->prepare("UPDATE users SET password = :pwd WHERE id = :id");
            $pStmt->execute([':pwd' => $newHash, ':id' => $id]);
        }

        AuditLogger::log($request, 'UPDATE', 'User', (string)$id, null, $data);
        Response::success(null, 'User updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'users.manage');
        $id = (int)$request->getParam('id');

        // Cannot delete self
        if ($id === $request->getUserId()) {
            Response::error("You cannot delete your own account.", 400);
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE users SET deleted_at = NOW(), is_active = 0 WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'User', (string)$id);
        Response::success(null, 'User deleted successfully');
    }
}
