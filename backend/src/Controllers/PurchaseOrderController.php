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

class PurchaseOrderController
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
        $supplierId = $request->getQuery('supplier_id');

        $where = ["po.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(po.po_number LIKE :srch OR s.name LIKE :srch OR po.remarks LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($status) {
            $where[] = "po.status = :status";
            $params[':status'] = $status;
        }

        if ($supplierId) {
            $where[] = "po.supplier_id = :sup";
            $params[':sup'] = $supplierId;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_orders po JOIN suppliers s ON po.supplier_id = s.id WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT po.*, s.name as supplier_name, s.code as supplier_code,
                                          u.name as creator_name, ap.name as approver_name
                                   FROM purchase_orders po
                                   JOIN suppliers s ON po.supplier_id = s.id
                                   LEFT JOIN users u ON po.created_by = u.id
                                   LEFT JOIN users ap ON po.approved_by = ap.id
                                   WHERE $whereSql
                                   ORDER BY po.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $orders = $dataStmt->fetchAll();

        Response::success($orders, 'Purchase orders retrieved', 200, [
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

        $stmt = $pdo->prepare("SELECT po.*, s.name as supplier_name, s.code as supplier_code, s.email as supplier_email,
                                      s.phone as supplier_phone, s.address as supplier_address,
                                      u.name as creator_name, ap.name as approver_name,
                                      pr.requisition_number
                               FROM purchase_orders po
                               JOIN suppliers s ON po.supplier_id = s.id
                               LEFT JOIN users u ON po.created_by = u.id
                               LEFT JOIN users ap ON po.approved_by = ap.id
                               LEFT JOIN purchase_requisitions pr ON po.requisition_id = pr.id
                               WHERE po.id = :id AND po.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $po = $stmt->fetch();
        if (!$po) {
            Response::notFound("Purchase order not found");
        }

        $itemsStmt = $pdo->prepare("SELECT poi.*, p.code as product_code, p.name as product_name, u.symbol as unit_symbol
                                    FROM purchase_order_items poi
                                    JOIN products p ON poi.product_id = p.id
                                    LEFT JOIN units u ON p.unit_id = u.id
                                    WHERE poi.po_id = :id");
        $itemsStmt->execute([':id' => $id]);
        $po['items'] = $itemsStmt->fetchAll();

        Response::success($po);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.create');
        $data = Validator::validate($request->body(), [
            'supplier_id' => 'required|integer',
            'date'        => 'required',
            'items'       => 'required',
        ]);

        $items = $request->get('items');
        if (!is_array($items) || empty($items)) {
            Response::error("Purchase order must have at least one line item.", 422);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $companyId = $request->getCompanyId();
            $poNum = SequenceHelper::getNextNumber($companyId, 'PO', 'PO');

            // Calculate totals
            $totalAmount = 0.0;
            $taxAmount = 0.0;
            $discountAmount = 0.0;

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $tax = (float)($item['tax_amount'] ?? 0);
                $disc = (float)($item['discount_amount'] ?? 0);

                $lineTotal = ($qty * $price) + $tax - $disc;
                $totalAmount += ($qty * $price);
                $taxAmount += $tax;
                $discountAmount += $disc;
            }
            $netAmount = $totalAmount + $taxAmount - $discountAmount;

            $reqId = $request->get('requisition_id') ?: null;

            $stmt = $pdo->prepare("INSERT INTO purchase_orders (company_id, branch_id, po_number, requisition_id, 
                                                              supplier_id, date, expected_delivery_date, status, 
                                                              total_amount, tax_amount, discount_amount, net_amount, 
                                                              remarks, created_by) 
                                   VALUES (:cid, :bid, :num, :rid, :sid, :dt, :exp, 'draft', :tot, :tax, :disc, :net, :rem, :uid)");
            $stmt->execute([
                ':cid'  => $companyId,
                ':bid'  => $request->getBranchId(),
                ':num'  => $poNum,
                ':rid'  => $reqId,
                ':sid'  => $data['supplier_id'],
                ':dt'   => $data['date'],
                ':exp'  => $request->get('expected_delivery_date'),
                ':tot'  => $totalAmount,
                ':tax'  => $taxAmount,
                ':disc' => $discountAmount,
                ':net'  => $netAmount,
                ':rem'  => $request->get('remarks'),
                ':uid'  => $request->getUserId(),
            ]);
            $poId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, quantity, received_quantity, 
                                                                       unit_price, tax_rate, tax_amount, discount_amount, total_price) 
                                       VALUES (:poid, :pid, :qty, 0, :price, :trate, :tax, :disc, :tot)");

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? 0);
                $tax = (float)($item['tax_amount'] ?? 0);
                $disc = (float)($item['discount_amount'] ?? 0);
                $lineTotal = ($qty * $price) + $tax - $disc;

                $itemStmt->execute([
                    ':poid'  => $poId,
                    ':pid'   => $item['product_id'],
                    ':qty'   => $qty,
                    ':price' => $price,
                    ':trate' => $taxRate,
                    ':tax'   => $tax,
                    ':disc'  => $disc,
                    ':tot'   => $lineTotal,
                ]);
            }

            Database::commit();
            AuditLogger::log($request, 'CREATE', 'PurchaseOrder', (string)$poId, null, ['number' => $poNum]);
            Response::created(['id' => $poId, 'po_number' => $poNum], 'Purchase order created successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to create purchase order: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.create');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $status = $stmt->fetchColumn();

        if (!$status) {
            Response::notFound("Purchase order not found");
        }
        if ($status !== 'draft') {
            Response::error("Cannot edit a purchase order with status '$status'.", 400);
        }

        $items = $request->get('items');
        if (!is_array($items) || empty($items)) {
            Response::error("Purchase order must have line items.", 422);
        }

        Database::beginTransaction();
        try {
            $totalAmount = 0.0;
            $taxAmount = 0.0;
            $discountAmount = 0.0;

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $tax = (float)($item['tax_amount'] ?? 0);
                $disc = (float)($item['discount_amount'] ?? 0);

                $totalAmount += ($qty * $price);
                $taxAmount += $tax;
                $discountAmount += $disc;
            }
            $netAmount = $totalAmount + $taxAmount - $discountAmount;

            $upd = $pdo->prepare("UPDATE purchase_orders SET supplier_id = :sid, date = :dt, expected_delivery_date = :exp, 
                                 total_amount = :tot, tax_amount = :tax, discount_amount = :disc, net_amount = :net, 
                                 remarks = :rem, updated_at = NOW() WHERE id = :id");
            $upd->execute([
                ':sid'  => $request->get('supplier_id'),
                ':dt'   => $request->get('date'),
                ':exp'  => $request->get('expected_delivery_date'),
                ':tot'  => $totalAmount,
                ':tax'  => $taxAmount,
                ':disc' => $discountAmount,
                ':net'  => $netAmount,
                ':rem'  => $request->get('remarks'),
                ':id'   => $id,
            ]);

            $del = $pdo->prepare("DELETE FROM purchase_order_items WHERE po_id = :id");
            $del->execute([':id' => $id]);

            $itemStmt = $pdo->prepare("INSERT INTO purchase_order_items (po_id, product_id, quantity, received_quantity, 
                                                                       unit_price, tax_rate, tax_amount, discount_amount, total_price) 
                                       VALUES (:poid, :pid, :qty, 0, :price, :trate, :tax, :disc, :tot)");
            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? 0);
                $tax = (float)($item['tax_amount'] ?? 0);
                $disc = (float)($item['discount_amount'] ?? 0);
                $lineTotal = ($qty * $price) + $tax - $disc;

                $itemStmt->execute([
                    ':poid'  => $id,
                    ':pid'   => $item['product_id'],
                    ':qty'   => $qty,
                    ':price' => $price,
                    ':trate' => $taxRate,
                    ':tax'   => $tax,
                    ':disc'  => $disc,
                    ':tot'   => $lineTotal,
                ]);
            }

            Database::commit();
            AuditLogger::log($request, 'UPDATE', 'PurchaseOrder', (string)$id);
            Response::success(null, 'Purchase order updated successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to update purchase order: ' . $e->getMessage(), 500);
        }
    }

    public function approve(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.approve');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'approved', approved_by = :uid, 
                               approved_at = NOW(), updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid AND status = 'draft'");
        $stmt->execute([
            ':uid' => $request->getUserId(),
            ':id'  => $id,
            ':cid' => $request->getCompanyId(),
        ]);

        if ($stmt->rowCount() === 0) {
            Response::error("Purchase order is not in draft status or not found.", 400);
        }

        AuditLogger::log($request, 'APPROVE', 'PurchaseOrder', (string)$id, null, ['status' => 'approved']);
        Response::success(null, 'Purchase order approved successfully');
    }

    public function cancel(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.approve');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'cancelled', updated_at = NOW() 
                               WHERE id = :id AND company_id = :cid AND status IN ('draft', 'approved')");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);

        if ($stmt->rowCount() === 0) {
            Response::error("Cannot cancel this purchase order.", 400);
        }

        AuditLogger::log($request, 'CANCEL', 'PurchaseOrder', (string)$id);
        Response::success(null, 'Purchase order cancelled');
    }

    public function delete(Request $request): void
    {
        PermissionMiddleware::require($request, 'purchase.create');
        $id = (int)$request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT status FROM purchase_orders WHERE id = :id AND company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $status = $stmt->fetchColumn();

        if ($status !== 'draft') {
            Response::error("Only draft purchase orders can be deleted.", 400);
        }

        $del = $pdo->prepare("DELETE FROM purchase_orders WHERE id = :id");
        $del->execute([':id' => $id]);

        AuditLogger::log($request, 'DELETE', 'PurchaseOrder', (string)$id);
        Response::success(null, 'Purchase order deleted successfully');
    }
}
