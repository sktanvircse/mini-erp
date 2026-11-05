<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\PermissionMiddleware;

class AuditLogController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'audit.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 20)));
        $offset = ($page - 1) * $perPage;

        $module = $request->getQuery('module');
        $action = $request->getQuery('action');
        $search = trim((string)$request->getQuery('search', ''));

        $where = ["a.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($module) {
            $where[] = "a.module = :mod";
            $params[':mod'] = $module;
        }

        if ($action) {
            $where[] = "a.action = :act";
            $params[':act'] = $action;
        }

        if ($search !== '') {
            $where[] = "(u.name LIKE :srch OR a.record_id LIKE :srch OR a.module LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON a.user_id = u.id WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT a.*, u.name as user_name, u.email as user_email
                                   FROM audit_logs a
                                   LEFT JOIN users u ON a.user_id = u.id
                                   WHERE $whereSql
                                   ORDER BY a.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $logs = $dataStmt->fetchAll();

        Response::success($logs, 'Audit logs retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }
}
