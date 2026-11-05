<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\AuditLogger;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\SequenceHelper;
use App\Core\Validator;
use App\Middleware\PermissionMiddleware;
use Exception;
use PDO;

class PurchaseRequisitionController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $status = $request->getQuery('status');

        $where = ["pr.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(pr.requisition_number LIKE :srch OR pr.remarks LIKE :srch OR u.name LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($status) {
            $where[] = "pr.status = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_requisitions pr LEFT JOIN users u ON pr.requested_by = u.id WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT pr.*, u.name as requester_name, ap.name as approver_name,
                                          COUNT(pri.id) as item_count,
                                          COALESCE(SUM(pri.quantity * pri.estimated_unit_cost), 0) as total_estimated_cost
                                   FROM purchase_requisitions pr
                                   JOIN users u ON pr.requested_by = u.id
                                   LEFT JOIN users ap ON pr.approved_by = ap.id
                                   LEFT JOIN purchase_requisition_items pri ON pr.id = pri.requisition_id
                                   WHERE $whereSql
                                   GROUP BY pr.id
                                   ORDER BY pr.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $requisitions = $dataStmt->fetchAll();

        Response::success($requisitions, 'Requisitions retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT pr.*, u.name as requester_name, ap.name as approver_name
                               FROM purchase_requisitions pr
                               JOIN users u ON pr.requested_by = u.id
                               LEFT JOIN users ap ON pr.approved_by = ap.id
                               WHERE pr.id = :id AND pr.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $requisition = $stmt->fetch();
        if (!$requisition) {
            Response::notFound("Purchase requisition not found");
        }

        $itemsStmt = $pdo->prepare("SELECT pri.*, p.code as product_code, p.name as product_name, u.symbol as unit_symbol
                                    FROM purchase_requisition_items pri
                                    JOIN products p ON pri.product_id = p.id
                                    LEFT JOIN units u ON p.unit_id = u.id
                                    WHERE pri.requisition_id = :id");
        $itemsStmt->execute([':id' => $id]);
        $requisition['items'] = $itemsStmt->fetchAll();

        Response::success($requisition);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.create');
        $data = Validator::validate($request->body(), [
            'date'  => 'required',
            'items' => 'required',
        ]);

        $items = $request->get('items');
        if (!is_array($items) || empty($items)) {
            Response::error("Requisition must have at least one product item.", 422);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $companyId = $request->getCompanyId();
            $reqNum = SequenceHelper::getNextNumber($companyId, 'PR', 'PR');

            $stmt = $pdo->prepare("INSERT INTO purchase_requisitions (company_id, branch_id, requisition_number, 
                                                                    requested_by, date, status, remarks) 
                                   VALUES (:cid, :bid, :num, :uid, :dt, 'draft', :rem)");
            $stmt->execute([
                ':cid' => $companyId,
                ':bid' => $request->getBranchId(),
                ':num' => $reqNum,
                ':uid' => $request->getUserId(),
                ':dt'  => $data['date'],
                ':rem' => $request->get('remarks'),
            ]);
            $reqId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO purchase_requisition_items (requisition_id, product_id, quantity, estimated_unit_cost, remarks) 
                                       VALUES (:rid, :pid, :qty, :cost, :rem)");

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                if ($qty <= 0) continue;

                $itemStmt->execute([
                    ':rid'  => $reqId,
                    ':pid'  => $item['product_id'],
                    ':qty'  => $qty,
                    ':cost' => (float)($item['estimated_unit_cost'] ?? 0),
                    ':rem'  => $item['remarks'] ?? null,
                ]);
            }

            Database::commit();
            AuditLogger::log($request, 'CREATE', 'PurchaseRequisition', (string)$reqId, null, ['number' => $reqNum]);
            Response::created(['id' => $reqId, 'requisition_number' => $reqNum], 'Purchase requisition created successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to create purchase requisition: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.create');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM purchase_requisitions WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $status = $stmt->fetchColumn();

        if (!$status) {
            Response::notFound("Requisition not found");
        }
        if ($status !== 'draft') {
            Response::error("Cannot edit a requisition that is already $status.", 400);
        }

        Database::beginTransaction();
        try {
            $upd = $pdo->prepare("UPDATE purchase_requisitions SET date = :dt, remarks = :rem, updated_at = NOW() WHERE id = :id");
            $upd->execute([
                ':dt'  => $request->get('date'),
                ':rem' => $request->get('remarks'),
                ':id'  => $id,
            ]);

            $items = $request->get('items');
            if (is_array($items) && !empty($items)) {
                $del = $pdo->prepare("DELETE FROM purchase_requisition_items WHERE requisition_id = :id");
                $del->execute([':id' => $id]);

                $itemStmt = $pdo->prepare("INSERT INTO purchase_requisition_items (requisition_id, product_id, quantity, estimated_unit_cost, remarks) 
                                           VALUES (:rid, :pid, :qty, :cost, :rem)");
                foreach ($items as $item) {
                    $qty = (float)($item['quantity'] ?? 0);
                    if ($qty <= 0) continue;
                    $itemStmt->execute([
                        ':rid'  => $id,
                        ':pid'  => $item['product_id'],
                        ':qty'  => $qty,
                        ':cost' => (float)($item['estimated_unit_cost'] ?? 0),
                        ':rem'  => $item['remarks'] ?? null,
                    ]);
                }
            }

            Database::commit();
            AuditLogger::log($request, 'UPDATE', 'PurchaseRequisition', (string)$id);
            Response::success(null, 'Requisition updated successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to update requisition: ' . $e->getMessage(), 500);
        }
    }

    public function approve(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.approve');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE purchase_requisitions SET status = 'approved', approved_by = :uid, 
                               approved_at = NOW(), updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid AND status IN ('draft', 'recommended')");
        $stmt->execute([
            ':uid' => $request->getUserId(),
            ':id'  => $id,
            ':cid' => $request->getCompanyId(),
        ]);

        if ($stmt->rowCount() === 0) {
            Response::error("Requisition is not eligible for approval or not found.", 400);
        }

        AuditLogger::log($request, 'APPROVE', 'PurchaseRequisition', (string)$id, null, ['status' => 'approved']);
        Response::success(null, 'Purchase requisition approved successfully');
    }

    public function reject(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.approve');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE purchase_requisitions SET status = 'rejected', approved_by = :uid, 
                               approved_at = NOW(), remarks = CONCAT(COALESCE(remarks, ''), ' [Rejected: ', :reason, ']'), updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid AND status IN ('draft', 'recommended')");
        $stmt->execute([
            ':uid'    => $request->getUserId(),
            ':reason' => $request->get('reason', 'Rejected by manager'),
            ':id'     => $id,
            ':cid'    => $request->getCompanyId(),
        ]);

        if ($stmt->rowCount() === 0) {
            Response::error("Requisition is not eligible for rejection or not found.", 400);
        }

        AuditLogger::log($request, 'REJECT', 'PurchaseRequisition', (string)$id, null, ['status' => 'rejected']);
        Response::success(null, 'Purchase requisition rejected');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.create');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM purchase_requisitions WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $status = $stmt->fetchColumn();

        if ($status !== 'draft') {
            Response::error("Only draft requisitions can be deleted.", 400);
        }

        $del = $pdo->prepare("DELETE FROM purchase_requisitions WHERE id = :id");
        $del->execute([':id' => $id]);

        AuditLogger::log($request, 'DELETE', 'PurchaseRequisition', (string)$id);
        Response::success(null, 'Purchase requisition deleted successfully');
    }
}
