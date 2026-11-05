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

class SupplierController
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

        $where = ["s.company_id = :cid", "s.deleted_at IS NULL"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(s.code LIKE :srch OR s.name LIKE :srch OR s.email LIKE :srch OR s.phone LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($status !== null && $status !== '') {
            $where[] = "s.is_active = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers s WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT s.* FROM suppliers s WHERE $whereSql ORDER BY s.id DESC LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $suppliers = $dataStmt->fetchAll();

        Response::success($suppliers, 'Suppliers retrieved', 200, [
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
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id AND company_id = :cid AND deleted_at IS NULL");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $supplier = $stmt->fetch();
        if (!$supplier) {
            Response::notFound("Supplier not found");
        }
        Response::success($supplier);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'suppliers.manage');
        $data = Validator::validate($request->body(), [
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $code = $request->get('code');
        if (!$code) {
            $seqStmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE company_id = :cid");
            $seqStmt->execute([':cid' => $request->getCompanyId()]);
            $count = (int)$seqStmt->fetchColumn() + 1;
            $code = sprintf('SUP-%03d', $count);
        }

        $openingBal = (float)$request->get('opening_balance', 0);

        $stmt = $pdo->prepare("INSERT INTO suppliers (company_id, code, name, email, phone, address, tax_number, 
                                                     opening_balance, current_balance, is_active) 
                               VALUES (:cid, :code, :name, :email, :phone, :address, :tax, :op, :cur, :act)");
        $stmt->execute([
            ':cid'     => $request->getCompanyId(),
            ':code'    => $code,
            ':name'    => $data['name'],
            ':email'   => $request->get('email'),
            ':phone'   => $request->get('phone'),
            ':address' => $request->get('address'),
            ':tax'     => $request->get('tax_number'),
            ':op'      => $openingBal,
            ':cur'     => $openingBal,
            ':act'     => (int)$request->get('is_active', 1),
        ]);
        $newId = (int)$pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE', 'Supplier', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'Supplier created successfully');
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'suppliers.manage');
        $id = (int)$request->getParam('id');
        $data = Validator::validate($request->body(), [
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE suppliers SET name = :name, email = :email, phone = :phone, address = :address, 
                               tax_number = :tax, is_active = :act, updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid");
        $stmt->execute([
            ':name'    => $data['name'],
            ':email'   => $request->get('email'),
            ':phone'   => $request->get('phone'),
            ':address' => $request->get('address'),
            ':tax'     => $request->get('tax_number'),
            ':act'     => (int)$request->get('is_active', 1),
            ':id'      => $id,
            ':cid'     => $request->getCompanyId(),
        ]);

        AuditLogger::log($request, 'UPDATE', 'Supplier', (string)$id, null, $data);
        Response::success(null, 'Supplier updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'suppliers.manage');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE suppliers SET deleted_at = NOW(), is_active = 0 WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'Supplier', (string)$id);
        Response::success(null, 'Supplier deleted successfully');
    }
}
