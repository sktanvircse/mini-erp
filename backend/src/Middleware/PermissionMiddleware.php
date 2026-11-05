<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class PermissionMiddleware
{
    public static function require(Request $request, string $permissionCode): void
    {
        $user = $request->getUser();
        if (!$user) {
            Response::unauthorized("Authentication required.");
        }

        // Admin role has superuser bypass
        if (($user['role_name'] ?? '') === 'Admin') {
            return;
        }

        $permissions = $user['permissions'] ?? [];
        if (!in_array($permissionCode, $permissions, true)) {
            Response::forbidden("Access denied: You lack the '$permissionCode' permission.");
        }
    }

    public static function has(Request $request, string $permissionCode): bool
    {
        $user = $request->getUser();
        if (!$user) {
            return false;
        }

        if (($user['role_name'] ?? '') === 'Admin') {
            return true;
        }

        return in_array($permissionCode, $user['permissions'] ?? [], true);
    }
}
