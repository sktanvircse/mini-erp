<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Database;
use App\Core\Env;
use App\Core\JWT;
use App\Core\Request;
use App\Core\Response;
use PDO;

class AuthMiddleware
{
    public function handle(Request $request): void
    {
        $token = $request->getBearerToken();
        if (!$token) {
            Response::unauthorized("Bearer token is missing or malformed.");
        }

        $secret = Env::get('JWT_SECRET', 'default_secret');
        $payload = JWT::decode($token, $secret);

        if (!$payload || !isset($payload['sub'])) {
            Response::unauthorized("Invalid or expired session token.");
        }

        $userId = (int)$payload['sub'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT u.id, u.company_id, u.branch_id, u.role_id, u.name, u.email, u.phone, 
                                      u.is_active, u.force_password_change, r.name as role_name
                               FROM users u
                               JOIN roles r ON u.role_id = r.id
                               WHERE u.id = :id AND u.is_active = 1 AND u.deleted_at IS NULL");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::unauthorized("User account is inactive or not found.");
        }

        // Fetch user's permissions
        $permStmt = $pdo->prepare("SELECT p.code 
                                   FROM role_permissions rp
                                   JOIN permissions p ON rp.permission_id = p.id
                                   WHERE rp.role_id = :role_id");
        $permStmt->execute([':role_id' => $user['role_id']]);
        $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);

        $user['permissions'] = $permissions;
        $request->setUser($user);
    }
}
