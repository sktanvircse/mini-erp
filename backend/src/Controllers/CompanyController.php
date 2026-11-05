<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;

class CompanyController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'settings.manage');
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT * FROM companies ORDER BY id ASC");
        Response::success($stmt->fetchAll());
    }

    public function show(Request $request): void
    {
        $id = $request->getParam('id', $request->getCompanyId());
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $company = $stmt->fetch();
        if (!$company) {
            Response::notFound("Company not found");
        }
        Response::success($company);
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'settings.manage');
        $id = (int)$request->getParam('id', $request->getCompanyId());
        $data = Validator::validate($request->body(), [
            'name'     => 'required',
            'email'    => 'required|email',
            'currency' => 'required',
        ]);

        $pdo = Database::getConnection();
        $phone = $request->get('phone');
        $address = $request->get('address');

        $stmt = $pdo->prepare("UPDATE companies SET name = :name, email = :email, phone = :phone, 
                               address = :address, currency = :currency, updated_at = NOW() WHERE id = :id");
        $stmt->execute([
            ':name'     => $data['name'],
            ':email'    => $data['email'],
            ':phone'    => $phone,
            ':address'  => $address,
            ':currency' => $data['currency'],
            ':id'       => $id,
        ]);

        AuditLogger::log($request, 'UPDATE', 'Company', (string)$id, null, $data);
        Response::success(null, 'Company profile updated');
    }
}
