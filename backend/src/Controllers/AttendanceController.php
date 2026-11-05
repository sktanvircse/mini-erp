<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;
use Exception;
use PDO;

class AttendanceController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $date = $request->getQuery('date', date('Y-m-d'));
        $employeeId = $request->getQuery('employee_id');

        $where = ["a.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($date) {
            $where[] = "a.date = :dt";
            $params[':dt'] = $date;
        }

        if ($employeeId) {
            $where[] = "a.employee_id = :emp";
            $params[':emp'] = $employeeId;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT a.*, e.employee_code, e.first_name, e.last_name, d.name as department_name, des.name as designation_name
                FROM attendance a
                JOIN employees e ON a.employee_id = e.id
                JOIN departments d ON e.department_id = d.id
                JOIN designations des ON e.designation_id = des.id
                WHERE $whereSql
                ORDER BY e.first_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $records = $stmt->fetchAll();

        Response::success($records);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.attendance');
        $data = Validator::validate($request->body(), [
            'employee_id' => 'required|integer',
            'date'        => 'required',
            'status'      => 'required|in:present,absent,late,half_day,on_leave',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO attendance (company_id, employee_id, date, check_in, check_out, status, remarks) 
                               VALUES (:cid, :eid, :dt, :cin, :cout, :st, :rem) 
                               ON DUPLICATE KEY UPDATE check_in = VALUES(check_in), check_out = VALUES(check_out), 
                                                       status = VALUES(status), remarks = VALUES(remarks), updated_at = NOW()");
        $stmt->execute([
            ':cid'  => $request->getCompanyId(),
            ':eid'  => $data['employee_id'],
            ':dt'   => $data['date'],
            ':cin'  => $request->get('check_in'),
            ':cout' => $request->get('check_out'),
            ':st'   => $data['status'],
            ':rem'  => $request->get('remarks'),
        ]);

        AuditLogger::log($request, 'ATTENDANCE', 'Attendance', (string)$data['employee_id'], null, $data);
        Response::success(null, 'Attendance recorded successfully');
    }

    public function bulkStore(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.attendance');
        $data = Validator::validate($request->body(), [
            'date'    => 'required',
            'records' => 'required',
        ]);

        $records = $request->get('records');
        if (!is_array($records) || empty($records)) {
            Response::error("Records array cannot be empty.", 422);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $companyId = $request->getCompanyId();
            $date = $data['date'];

            $stmt = $pdo->prepare("INSERT INTO attendance (company_id, employee_id, date, check_in, check_out, status, remarks) 
                                   VALUES (:cid, :eid, :dt, :cin, :cout, :st, :rem) 
                                   ON DUPLICATE KEY UPDATE check_in = VALUES(check_in), check_out = VALUES(check_out), 
                                                           status = VALUES(status), remarks = VALUES(remarks), updated_at = NOW()");

            foreach ($records as $r) {
                $eid = (int)($r['employee_id'] ?? 0);
                if ($eid <= 0) continue;

                $stmt->execute([
                    ':cid'  => $companyId,
                    ':eid'  => $eid,
                    ':dt'   => $date,
                    ':cin'  => $r['check_in'] ?? null,
                    ':cout' => $r['check_out'] ?? null,
                    ':st'   => $r['status'] ?? 'present',
                    ':rem'  => $r['remarks'] ?? null,
                ]);
            }

            Database::commit();
            AuditLogger::log($request, 'BULK_ATTENDANCE', 'Attendance', $date, null, ['count' => count($records)]);
            Response::success(null, 'Bulk attendance saved successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to save bulk attendance: ' . $e->getMessage(), 500);
        }
    }
}
