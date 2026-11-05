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

class RoleController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'roles.manage');
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT r.*, COUNT(u.id) as user_count 
                             FROM roles r 
                             LEFT JOIN users u ON r.id = u.role_id AND u.deleted_at IS NULL
                             GROUP BY r.id 
                             ORDER BY r.id ASC");
        Response::success($stmt->fetchAll());
    }

    public function permissions(Request $request): void
    {
        PermissionMiddleware::require($request, 'roles.manage');
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT id, code, name, code as `key`, name as `label`, module, description, created_at 
                             FROM permissions 
                             ORDER BY module ASC, id ASC");
        $all = $stmt->fetchAll();

        // Group by module
        $grouped = [];
        foreach ($all as $p) {
            $grouped[$p['module']][] = $p;
        }

        Response::success([
            'all'     => $all,
            'grouped' => $grouped,
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'roles.manage');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT * FROM roles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $role = $stmt->fetch();
        if (!$role) {
            Response::notFound("Role not found");
        }

        $pStmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :id");
        $pStmt->execute([':id' => $id]);
        $role['permission_ids'] = $pStmt->fetchAll(PDO::FETCH_COLUMN);

        Response::success($role);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'roles.manage');
        $data = Validator::validate($request->body(), [
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO roles (company_id, name, description, is_system) 
                                   VALUES (:cid, :name, :desc, 0)");
            $stmt->execute([
                ':cid'  => $request->getCompanyId(),
                ':name' => $data['name'],
                ':desc' => $request->get('description'),
            ]);
            $roleId = (int)$pdo->lastInsertId();

            $permIds = $request->get('permission_ids', []);
            if (is_array($permIds) && !empty($permIds)) {
                $pIns = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)");
                foreach ($permIds as $pid) {
                    $pIns->execute([':rid' => $roleId, ':pid' => $pid]);
                }
            }

            Database::commit();
            AuditLogger::log($request, 'CREATE', 'Role', (string)$roleId, null, $data);
            Response::created(['id' => $roleId], 'Role created successfully');
        } catch (\Exception $e) {
            Database::rollBack();
            Response::error('Failed to create role: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'roles.manage');
        $id = (int)$request->getParam('id');
        $data = Validator::validate($request->body(), [
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE roles SET name = :name, description = :desc, updated_at = NOW() WHERE id = :id");
            $stmt->execute([
                ':name' => $data['name'],
                ':desc' => $request->get('description'),
                ':id'   => $id,
            ]);

            // Sync permissions
            $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = :id");
            $del->execute([':id' => $id]);

            $permIds = $request->get('permission_ids', []);
            if (is_array($permIds) && !empty($permIds)) {
                $pIns = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:rid, :pid)");
                foreach ($permIds as $pid) {
                    $pIns->execute([':rid' => $id, ':pid' => $pid]);
                }
            }

            Database::commit();
            AuditLogger::log($request, 'UPDATE', 'Role', (string)$id, null, $data);
            Response::success(null, 'Role updated successfully');
        } catch (\Exception $e) {
            Database::rollBack();
            Response::error('Failed to update role: ' . $e->getMessage(), 500);
        }
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'roles.manage');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT is_system FROM roles WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $isSystem = (int)$stmt->fetchColumn();

        if ($isSystem === 1) {
            Response::error("System default roles cannot be deleted.", 400);
        }

        $chk = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role_id = :id AND deleted_at IS NULL");
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() > 0) {
            Response::error("Cannot delete role assigned to active users.", 400);
        }

        $del = $pdo->prepare("DELETE FROM roles WHERE id = :id");
        $del->execute([':id' => $id]);

        AuditLogger::log($request, 'DELETE', 'Role', (string)$id);
        Response::success(null, 'Role deleted successfully');
    }

    public function permissionsByRole(Request $request): void
    {
        PermissionMiddleware::require($request, 'roles.manage');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT p.id, p.code, p.name, p.code as `key`, p.name as `label`, p.module, p.description
                               FROM permissions p
                               JOIN role_permissions rp ON p.id = rp.permission_id
                               WHERE rp.role_id = :id
                               ORDER BY p.module, p.code");
        $stmt->execute([':id' => $id]);
        $permissions = $stmt->fetchAll();

        Response::success($permissions);
    }

    public function syncPermissions(Request $request): void
    {
        PermissionMiddleware::require($request, 'roles.manage');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $perms = $request->get('permissions', []);
        Database::beginTransaction();
        try {
            $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = :id");
            $del->execute([':id' => $id]);

            if (is_array($perms) && !empty($perms)) {
                $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) 
                                      SELECT :rid, id FROM permissions WHERE code = :pcode OR name = :pname OR id = :pid");
                foreach ($perms as $p) {
                    $ins->execute([
                        ':rid'   => $id,
                        ':pcode' => (string)$p,
                        ':pname' => (string)$p,
                        ':pid'   => is_numeric($p) ? (int)$p : -1
                    ]);
                }
            }

            Database::commit();
            AuditLogger::log($request, 'UPDATE_PERMISSIONS', 'Role', (string)$id, null, ['permissions' => $perms]);
            Response::success(null, 'Role permissions updated successfully');
        } catch (\Exception $e) {
            Database::rollBack();
            Response::error('Failed to update permissions: ' . $e->getMessage(), 500);
        }
    }
}
