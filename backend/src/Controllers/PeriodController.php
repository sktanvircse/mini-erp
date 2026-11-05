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

class PeriodController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.view');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM financial_periods WHERE company_id = :cid ORDER BY start_date DESC");
        $stmt->execute([':cid' => $request->getCompanyId()]);
        Response::success($stmt->fetchAll());
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.periods');
        $data = Validator::validate($request->body(), [
            'name'       => 'required',
            'start_date' => 'required',
            'end_date'   => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO financial_periods (company_id, name, start_date, end_date, status) 
                               VALUES (:cid, :name, :sdate, :edate, 'open')");
        $stmt->execute([
            ':cid'   => $request->getCompanyId(),
            ':name'  => $data['name'],
            ':sdate' => $data['start_date'],
            ':edate' => $data['end_date'],
        ]);
        $newId = (int)$pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE', 'FinancialPeriod', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'Financial period created successfully');
    }

    public function toggle(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.periods');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM financial_periods WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $current = $stmt->fetchColumn();

        if (!$current) {
            Response::notFound("Period not found");
        }

        $newStatus = ($current === 'open') ? 'closed' : 'open';
        $upd = $pdo->prepare("UPDATE financial_periods SET status = :st, updated_at = NOW() WHERE id = :id");
        $upd->execute([':st' => $newStatus, ':id' => $id]);

        AuditLogger::log($request, 'TOGGLE_STATUS', 'FinancialPeriod', (string)$id, null, ['status' => $newStatus]);
        Response::success(['status' => $newStatus], "Financial period is now $newStatus");
    }
}
