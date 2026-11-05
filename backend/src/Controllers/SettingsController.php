<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\PermissionMiddleware;
use PDO;

class SettingsController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'settings.manage');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT key_name, key_value FROM settings WHERE company_id = :cid");
        $stmt->execute([':cid' => $request->getCompanyId()]);
        $rows = $stmt->fetchAll();

        $settings = [];
        foreach ($rows as $r) {
            $settings[$r['key_name']] = $r['key_value'];
        }

        Response::success($settings);
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'settings.manage');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();
        $body = $request->body();

        $stmt = $pdo->prepare("INSERT INTO settings (company_id, key_name, key_value) 
                               VALUES (:cid, :k, :v) 
                               ON DUPLICATE KEY UPDATE key_value = VALUES(key_value), updated_at = NOW()");

        foreach ($body as $key => $val) {
            $stmt->execute([
                ':cid' => $companyId,
                ':k'   => (string)$key,
                ':v'   => is_array($val) ? json_encode($val) : (string)$val,
            ]);
        }

        AuditLogger::log($request, 'UPDATE', 'Settings', 'company_' . $companyId, null, $body);
        Response::success(null, 'Settings updated successfully');
    }
}
