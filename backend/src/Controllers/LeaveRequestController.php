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

class LeaveRequestController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $employeeId = $request->getQuery('employee_id');
        $status = $request->getQuery('status');

        $where = ["lr.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($employeeId) {
            $where[] = "lr.employee_id = :emp";
            $params[':emp'] = $employeeId;
        }

        if ($status) {
            $where[] = "lr.status = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests lr WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT lr.*, e.employee_code, e.first_name, e.last_name, 
                                          d.name as department_name, ap.name as approver_name
                                   FROM leave_requests lr
                                   JOIN employees e ON lr.employee_id = e.id
                                   JOIN departments d ON e.department_id = d.id
                                   LEFT JOIN users ap ON lr.approved_by = ap.id
                                   WHERE $whereSql
                                   ORDER BY lr.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $leaves = $dataStmt->fetchAll();

        Response::success($leaves, 'Leave requests retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.leave');
        $data = Validator::validate($request->body(), [
            'employee_id' => 'required|integer',
            'leave_type'  => 'required|in:casual,sick,annual,unpaid',
            'start_date'  => 'required',
            'end_date'    => 'required',
        ]);

        $days = (int)$request->get('total_days', 1);
        if ($days <= 0) {
            $days = 1;
        }

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO leave_requests (company_id, employee_id, leave_type, start_date, end_date, total_days, reason, status) 
                               VALUES (:cid, :eid, :type, :sdate, :edate, :days, :reason, 'pending')");
        $stmt->execute([
            ':cid'    => $request->getCompanyId(),
            ':eid'    => $data['employee_id'],
            ':type'   => $data['leave_type'],
            ':sdate'  => $data['start_date'],
            ':edate'  => $data['end_date'],
            ':days'   => $days,
            ':reason' => $request->get('reason'),
        ]);
        $newId = (int)$pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE_LEAVE', 'LeaveRequest', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'Leave request submitted successfully');
    }

    public function approve(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.leave');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'approved', approved_by = :uid, approved_at = NOW(), updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid AND status = 'pending'");
        $stmt->execute([':uid' => $request->getUserId(), ':id' => $id, ':cid' => $request->getCompanyId()]);

        if ($stmt->rowCount() === 0) {
            Response::error("Leave request is not eligible for approval or not found.", 400);
        }

        AuditLogger::log($request, 'APPROVE_LEAVE', 'LeaveRequest', (string)$id, null, ['status' => 'approved']);
        Response::success(null, 'Leave request approved');
    }

    public function reject(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.leave');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE leave_requests SET status = 'rejected', approved_by = :uid, approved_at = NOW(), updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid AND status = 'pending'");
        $stmt->execute([':uid' => $request->getUserId(), ':id' => $id, ':cid' => $request->getCompanyId()]);

        if ($stmt->rowCount() === 0) {
            Response::error("Leave request is not eligible for rejection or not found.", 400);
        }

        AuditLogger::log($request, 'REJECT_LEAVE', 'LeaveRequest', (string)$id, null, ['status' => 'rejected']);
        Response::success(null, 'Leave request rejected');
    }
}
