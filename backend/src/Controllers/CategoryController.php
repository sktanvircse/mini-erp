<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;

class CategoryController
{
    public function index(Request $request): void
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT c.*, COUNT(p.id) as product_count 
                               FROM categories c 
                               LEFT JOIN products p ON c.id = p.category_id AND p.deleted_at IS NULL
                               WHERE c.company_id = :cid 
                               GROUP BY c.id ORDER BY c.name ASC");
        $stmt->execute([':cid' => $request->getCompanyId()]);
        Response::success($stmt->fetchAll());
    }

    public function show(Request $request): void
    {
        $id = $request->getParam('id');
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $cat = $stmt->fetch();
        if (!$cat) {
            Response::notFound("Category not found");
        }
        Response::success($cat);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'master.view');
        $data = Validator::validate($request->body(), [
            'code' => 'required',
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO categories (company_id, code, name, description, is_active) 
                               VALUES (:cid, :code, :name, :desc, :act)");
        $stmt->execute([
            ':cid'  => $request->getCompanyId(),
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':desc' => $request->get('description'),
            ':act'  => (int)$request->get('is_active', 1),
        ]);
        $newId = (int)$pdo->lastInsertId();

        AuditLogger::log($request, 'CREATE', 'Category', (string)$newId, null, $data);
        Response::created(['id' => $newId], 'Category created successfully');
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'master.view');
        $id = (int)$request->getParam('id');
        $data = Validator::validate($request->body(), [
            'code' => 'required',
            'name' => 'required',
        ]);

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE categories SET code = :code, name = :name, description = :desc, 
                               is_active = :act, updated_at = NOW() WHERE id = :id AND company_id = :cid");
        $stmt->execute([
            ':code' => $data['code'],
            ':name' => $data['name'],
            ':desc' => $request->get('description'),
            ':act'  => (int)$request->get('is_active', 1),
            ':id'   => $id,
            ':cid'  => $request->getCompanyId(),
        ]);

        AuditLogger::log($request, 'UPDATE', 'Category', (string)$id, null, $data);
        Response::success(null, 'Category updated successfully');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'master.view');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $chk = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = :id AND deleted_at IS NULL");
        $chk->execute([':id' => $id]);
        if ((int)$chk->fetchColumn() > 0) {
            Response::error('Cannot delete category with associated products.', 400);
        }

        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        AuditLogger::log($request, 'DELETE', 'Category', (string)$id);
        Response::success(null, 'Category deleted successfully');
    }
}
