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

class CustomerController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'master.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $status = $request->getQuery('is_active');

        $where = ["c.company_id = :cid", "c.deleted_at IS NULL"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(c.code LIKE :srch OR c.name LIKE :srch OR c.email LIKE :srch OR c.phone LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($status !== null && $status !== '') {
            $where[] = "c.is_active = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM customers c WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT c.* FROM customers c WHERE $whereSql ORDER BY c.id DESC LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $customers = $dataStmt->fetchAll();

        Response::success($customers, 'Customers retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'master.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id AND company_id = :cid AND deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $customer = $stmt->fetch();
        if (!$customer) {
            Response::notFound("Customer not found");
        }
        Response::success($customer);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'customers.manage');
        $data = Validator::validate($request->body(), [
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $code = $request->get('code');
        if (!$code) {
            $seqStmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE company_id = :cid");
            $seqStmt->execute([':cid' => $request->getCompanyId()]);
            $count = (int)$seqStmt->fetchColumn() + 1;
            $code = sprintf('CUST-%03d', $count);
        }

        $openingBal = (float)$request->get('opening_balance', 0);
        $creditLimit = (float)$request->get('credit_limit', 0);

        $stmt = $pdo->prepare("INSERT INTO customers (company_id, code, name, email, phone, address, tax_number, 
                                                     credit_limit, opening_balance, current_balance, is_active) 
                               VALUES (:cid, :code, :name, :email, :phone, :address, :tax, :cl, :op, :cur, :act)");
        $stmt->execute([
            ':cid'   => $request->getCompanyId(),
            ':code'  => $code,
            ':name'  => $data['name'],
            ':email' => $request->get('email'),
            ':phone' => $request->get('phone'),
            ':address' => $request->get('address'),
            ':tax'   => $request->get('tax_number'),
            ':cl'    => $creditLimit,
            ':op'    => $openingBal,
            ':cur'   => $openingBal,
            ':act'   => (int)$request->get('is_active', 1),
        ]);
        $newId = (int)$pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE', 'Customer', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'Customer created successfully');
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'customers.manage');
        $id = (int)$request->getParam('id');
        $data = Validator::validate($request->body(), [
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE customers SET name = :name, email = :email, phone = :phone, address = :address, 
                               tax_number = :tax, credit_limit = :cl, is_active = :act, updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid");
        $stmt->execute([
            ':name'    => $data['name'],
            ':email'   => $request->get('email'),
            ':phone'   => $request->get('phone'),
            ':address' => $request->get('address'),
            ':tax'     => $request->get('tax_number'),
            ':cl'      => (float)$request->get('credit_limit', 0),
            ':act'     => (int)$request->get('is_active', 1),
            ':id'      => $id,
            ':cid'     => $request->getCompanyId(),
        ]);

        AuditLogger::log($request, 'UPDATE', 'Customer', (string)$id, null, $data);
        Response::success(null, 'Customer updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'customers.manage');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE customers SET deleted_at = NOW(), is_active = 0 WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'Customer', (string)$id);
        Response::success(null, 'Customer deleted successfully');
    }
}
