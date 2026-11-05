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

class AccountController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $sql = "SELECT coa.*, p.name as parent_name,
                       COALESCE((
                           SELECT SUM(vl.debit) - SUM(vl.credit) 
                           FROM voucher_lines vl 
                           JOIN vouchers v ON vl.voucher_id = v.id 
                           WHERE vl.account_id = coa.id AND v.status = 'posted' AND v.company_id = :cid1
                       ), 0) as balance
                FROM chart_of_accounts coa
                LEFT JOIN chart_of_accounts p ON coa.parent_id = p.id
                WHERE coa.company_id = :cid2
                ORDER BY coa.code ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cid1' => $companyId, ':cid2' => $companyId]);
        $accounts = $stmt->fetchAll();

        Response::success($accounts);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT coa.*, p.name as parent_name 
                               FROM chart_of_accounts coa
                               LEFT JOIN chart_of_accounts p ON coa.parent_id = p.id
                               WHERE coa.id = :id AND coa.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $account = $stmt->fetch();
        if (!$account) {
            Response::notFound("Account not found");
        }
        Response::success($account);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.coa');
        $data = Validator::validate($request->body(), [
            'code' => 'required',
            'name' => 'required',
            'type' => 'required|in:asset,liability,equity,income,expense',
        ]);

        $pdo = Database::getConnection();
        $parentId = $request->get('parent_id') ? (int)$request->get('parent_id') : null;

        $chk = $pdo->prepare("SELECT COUNT(*) FROM chart_of_accounts WHERE company_id = :cid AND code = :code");
        $chk->execute([':cid' => $request->getCompanyId(), ':code' => $data['code']]);
        if ((int)$chk->fetchColumn() > 0) {
            Response::error("Account code '{$data['code']}' already exists.", 422);
        }

        $stmt = $pdo->prepare("INSERT INTO chart_of_accounts (company_id, code, name, type, parent_id, is_active) 
                               VALUES (:cid, :code, :name, :type, :pid, :act)");
        $stmt->execute([
            ':cid'  => $request->getCompanyId(),
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':type' => $data['type'],
            ':pid'  => $parentId,
            ':act'  => (int)$request->get('is_active', 1),
        ]);
        $newId = (int)$pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE', 'ChartOfAccounts', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'Account created successfully');
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.coa');
        $id = (int)$request->getParam('id');
        $data = Validator::validate($request->body(), [
            'code' => 'required',
            'name' => 'required',
            'type' => 'required|in:asset,liability,equity,income,expense',
        ]);

        $pdo = Database::getConnection();
        $parentId = $request->get('parent_id') ? (int)$request->get('parent_id') : null;

        $stmt = $pdo->prepare("UPDATE chart_of_accounts SET code = :code, name = :name, type = :type, 
                               parent_id = :pid, is_active = :act, updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid");
        $stmt->execute([
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':type' => $data['type'],
            ':pid'  => $parentId,
            ':act'  => (int)$request->get('is_active', 1),
            ':id'   => $id,
            ':cid'  => $request->getCompanyId(),
        ]);

        AuditLogger::log($request, 'UPDATE', 'ChartOfAccounts', (string)$id, null, $data);
        Response::success(null, 'Account updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.coa');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $chk = $pdo->prepare("SELECT COUNT(*) FROM voucher_lines WHERE account_id = :id");
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() > 0) {
            Response::error('Cannot delete an account with existing ledger transaction lines.', 400);
        }

        $stmt = $pdo->prepare("DELETE FROM chart_of_accounts WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'ChartOfAccounts', (string)$id);
        Response::success(null, 'Account deleted successfully');
    }
}
