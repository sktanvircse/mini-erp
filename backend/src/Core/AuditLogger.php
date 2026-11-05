<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Exception;

class AuditLogger
{
    public static function log(
        Request $request,
        string $action,
        string $module,
        string|int|null $recordId = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            $pdo = Database::getConnection();
            $sql = "INSERT INTO `audit_logs` 
                    (`company_id`, `branch_id`, `user_id`, `action`, `module`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) 
                    VALUES (:cid, :bid, :uid, :act, :mod, :rec, :old, :new, :ip, :ua, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cid' => $request->getCompanyId(),
                ':bid' => $request->getBranchId(),
                ':uid' => $request->getUserId(),
                ':act' => strtoupper($action),
                ':mod' => $module,
                ':rec' => $recordId !== null ? (string)$recordId : null,
                ':old' => $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                ':new' => $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                ':ip'  => $request->getIp(),
                ':ua'  => substr($request->getUserAgent(), 0, 500),
            ]);
        } catch (Exception $e) {
            // Never break main flow on audit failure; log to error log
            error_log("Audit log failure: " . $e->getMessage());
        }
    }
}
