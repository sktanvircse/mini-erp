<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Env;
use App\Core\JWT;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use PDO;

class AuthController
{
    public function login(Request $request): void
    {
        $data = Validator::validate($request->body(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT u.id, u.company_id, u.branch_id, u.role_id, u.name, u.email, u.password, 
                                      u.phone, u.is_active, u.force_password_change, r.name as role_name,
                                      c.name as company_name, c.currency, b.name as branch_name
                               FROM users u
                               JOIN roles r ON u.role_id = r.id
                               JOIN companies c ON u.company_id = c.id
                               LEFT JOIN branches b ON u.branch_id = b.id
                               WHERE u.email = :email AND u.deleted_at IS NULL");
        $stmt->execute([':email' => $data['email']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            AuditLogger::log($request, 'LOGIN_FAILED', 'Auth', null, null, ['email' => $data['email']]);
            Response::error('Invalid credentials. Please verify your email and password.', 401);
        }

        if ((int)$user['is_active'] !== 1) {
            Response::error('Your account is currently disabled. Please contact administrator.', 403);
        }

        // Fetch permissions
        $permStmt = $pdo->prepare("SELECT p.code 
                                   FROM role_permissions rp 
                                   JOIN permissions p ON rp.permission_id = p.id 
                                   WHERE rp.role_id = :role_id");
        $permStmt->execute([':role_id' => $user['role_id']]);
        $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);

        $secret = Env::get('JWT_SECRET', 'default_secret');
        $expiry = (int)Env::get('JWT_EXPIRY', 86400);

        $payload = [
            'sub'        => $user['id'],
            'email'      => $user['email'],
            'role'       => $user['role_name'],
            'company_id' => $user['company_id'],
            'branch_id'  => $user['branch_id'],
        ];

        $token = JWT::encode($payload, $secret, $expiry);

        unset($user['password']);
        $user['permissions'] = $permissions;

        $request->setUser($user);
        AuditLogger::log($request, 'LOGIN_SUCCESS', 'Auth', (string)$user['id']);

        Response::success([
            'token' => $token,
            'user'  => $user,
        ], 'Login successful');
    }

    public function me(Request $request): void
    {
        $user = $request->getUser();
        $pdo = Database::getConnection();

        // Get available companies & branches
        $compStmt = $pdo->query("SELECT id, code, name, currency FROM companies WHERE is_active = 1");
        $companies = $compStmt->fetchAll();

        $branchStmt = $pdo->prepare("SELECT id, company_id, code, name FROM branches WHERE company_id = :cid AND is_active = 1");
        $branchStmt->execute([':cid' => $request->getCompanyId()]);
        $branches = $branchStmt->fetchAll();

        Response::success([
            'user'      => $user,
            'companies' => $companies,
            'branches'  => $branches,
        ]);
    }

    public function changePassword(Request $request): void
    {
        $userId = $request->getUserId();
        $data = Validator::validate($request->body(), [
            'current_password' => 'required',
            'new_password'     => 'required|min:6',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $hash = $stmt->fetchColumn();

        if (!password_verify($data['current_password'], (string)$hash)) {
            Response::error('Incorrect current password.', 400);
        }

        $newHash = password_hash($data['new_password'], PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password = :pwd, force_password_change = 0, updated_at = NOW() WHERE id = :id");
        $upd->execute([':pwd' => $newHash, ':id' => $userId]);

        AuditLogger::log($request, 'CHANGE_PASSWORD', 'Auth', (string)$userId);
        Response::success(null, 'Password updated successfully');
    }
}
