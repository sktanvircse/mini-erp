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

class EmployeeController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $departmentId = $request->getQuery('department_id');
        $designationId = $request->getQuery('designation_id');
        $status = $request->getQuery('is_active');

        $where = ["e.company_id = :cid", "e.deleted_at IS NULL"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(e.employee_code LIKE :srch OR e.first_name LIKE :srch OR e.last_name LIKE :srch OR e.email LIKE :srch OR e.phone LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($departmentId) {
            $where[] = "e.department_id = :dept";
            $params[':dept'] = $departmentId;
        }

        if ($designationId) {
            $where[] = "e.designation_id = :desig";
            $params[':desig'] = $designationId;
        }

        if ($status !== null && $status !== '') {
            $where[] = "e.is_active = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM employees e WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT e.*, d.name as department_name, des.name as designation_name, b.name as branch_name,
                                          (e.basic_salary + e.house_rent + e.medical_allowance + e.other_allowance) as gross_salary
                                   FROM employees e
                                   JOIN departments d ON e.department_id = d.id
                                   JOIN designations des ON e.designation_id = des.id
                                   LEFT JOIN branches b ON e.branch_id = b.id
                                   WHERE $whereSql
                                   ORDER BY e.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $employees = $dataStmt->fetchAll();

        Response::success($employees, 'Employees retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT e.*, d.name as department_name, des.name as designation_name, b.name as branch_name,
                                      (e.basic_salary + e.house_rent + e.medical_allowance + e.other_allowance) as gross_salary
                               FROM employees e
                               JOIN departments d ON e.department_id = d.id
                               JOIN designations des ON e.designation_id = des.id
                               LEFT JOIN branches b ON e.branch_id = b.id
                               WHERE e.id = :id AND e.company_id = :cid AND e.deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $employee = $stmt->fetch();
        if (!$employee) {
            Response::notFound("Employee not found");
        }
        Response::success($employee);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.manage');
        $data = Validator::validate($request->body(), [
            'first_name'     => 'required',
            'last_name'      => 'required',
            'department_id'  => 'required|integer',
            'designation_id' => 'required|integer',
            'joining_date'   => 'required',
            'basic_salary'   => 'required|numeric',
        ]);

        $pdo = Database::getConnection();
        $code = $request->get('employee_code');
        if (!$code) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE company_id = :cid");
            $cnt->execute([':cid' => $request->getCompanyId()]);
            $num = (int)$cnt->fetchColumn() + 1;
            $code = sprintf('EMP-%03d', $num);
        }

        $stmt = $pdo->prepare("INSERT INTO employees (company_id, branch_id, employee_code, first_name, last_name, 
                                                     email, phone, department_id, designation_id, joining_date, 
                                                     basic_salary, house_rent, medical_allowance, other_allowance, 
                                                     bank_account, is_active) 
                               VALUES (:cid, :bid, :code, :fn, :ln, :email, :phone, :dept, :desig, :jdt, 
                                       :basic, :rent, :med, :oth, :bank, :act)");
        $stmt->execute([
            ':cid'   => $request->getCompanyId(),
            ':bid'   => $request->get('branch_id') ?: null,
            ':code'  => $code,
            ':fn'    => $data['first_name'],
            ':ln'    => $data['last_name'],
            ':email' => $request->get('email'),
            ':phone' => $request->get('phone'),
            ':dept'  => $data['department_id'],
            ':desig' => $data['designation_id'],
            ':jdt'   => $data['joining_date'],
            ':basic' => (float)$data['basic_salary'],
            ':rent'  => (float)$request->get('house_rent', 0),
            ':med'   => (float)$request->get('medical_allowance', 0),
            ':oth'   => (float)$request->get('other_allowance', 0),
            ':bank'  => $request->get('bank_account'),
            ':act'   => (int)$request->get('is_active', 1),
        ]);
        $newId = (int)$pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE', 'Employee', (string)$newId, null, $data);
        Response::created(['id' => $newId, 'employee_code' => $code], 'Employee record created successfully');
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.manage');
        $id = (int)$request->getParam('id');
        $data = Validator::validate($request->body(), [
            'first_name'     => 'required',
            'last_name'      => 'required',
            'department_id'  => 'required|integer',
            'designation_id' => 'required|integer',
            'basic_salary'   => 'required|numeric',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE employees SET first_name = :fn, last_name = :ln, email = :email, phone = :phone, 
                               department_id = :dept, designation_id = :desig, branch_id = :bid, basic_salary = :basic, 
                               house_rent = :rent, medical_allowance = :med, other_allowance = :oth, bank_account = :bank, 
                               is_active = :act, updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid");
        $stmt->execute([
            ':fn'    => $data['first_name'],
            ':ln'    => $data['last_name'],
            ':email' => $request->get('email'),
            ':phone' => $request->get('phone'),
            ':dept'  => $data['department_id'],
            ':desig' => $data['designation_id'],
            ':bid'   => $request->get('branch_id') ?: null,
            ':basic' => (float)$data['basic_salary'],
            ':rent'  => (float)$request->get('house_rent', 0),
            ':med'   => (float)$request->get('medical_allowance', 0),
            ':oth'   => (float)$request->get('other_allowance', 0),
            ':bank'  => $request->get('bank_account'),
            ':act'   => (int)$request->get('is_active', 1),
            ':id'    => $id,
            ':cid'   => $request->getCompanyId(),
        ]);

        AuditLogger::log($request, 'UPDATE', 'Employee', (string)$id, null, $data);
        Response::success(null, 'Employee updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'hr.manage');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE employees SET deleted_at = NOW(), is_active = 0 WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'Employee', (string)$id);
        Response::success(null, 'Employee deleted successfully');
    }
}
