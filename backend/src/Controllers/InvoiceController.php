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

class InvoiceController
{
    public function index(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.view');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $page = max(1, (int)$request->getQuery('page', 1));
        $perPage = max(1, min(100, (int)$request->getQuery('per_page', 15)));
        $offset = ($page - 1) * $perPage;

        $search = trim((string)$request->getQuery('search', ''));
        $status = $request->getQuery('status');
        $customerId = $request->getQuery('customer_id');

        $where = ["i.company_id = :cid"];
        $params = [':cid' => $companyId];

        if ($search !== '') {
            $where[] = "(i.invoice_number LIKE :srch OR c.name LIKE :srch OR i.remarks LIKE :srch)";
            $params[':srch'] = "%$search%";
        }

        if ($status) {
            $where[] = "i.status = :status";
            $params[':status'] = $status;
        }

        if ($customerId) {
            $where[] = "i.customer_id = :cust";
            $params[':cust'] = $customerId;
        }

        $whereSql = implode(' AND ', $where);

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices i JOIN customers c ON i.customer_id = c.id WHERE $whereSql");
        $cntStmt->execute($params);
        $total = (int)$cntStmt->fetchColumn();

        $dataStmt = $pdo->prepare("SELECT i.*, c.name as customer_name, c.code as customer_code,
                                          so.so_number, del.delivery_number, u.name as creator_name,
                                          vr.voucher_number as receivable_voucher_no,
                                          vc.voucher_number as cost_voucher_no
                                   FROM invoices i
                                   JOIN customers c ON i.customer_id = c.id
                                   LEFT JOIN sales_orders so ON i.so_id = so.id
                                   LEFT JOIN deliveries del ON i.delivery_id = del.id
                                   LEFT JOIN users u ON i.created_by = u.id
                                   LEFT JOIN vouchers vr ON i.receivable_voucher_id = vr.id
                                   LEFT JOIN vouchers vc ON i.cost_voucher_id = vc.id
                                   WHERE $whereSql
                                   ORDER BY i.id DESC
                                   LIMIT $perPage OFFSET $offset");
        $dataStmt->execute($params);
        $invoices = $dataStmt->fetchAll();

        Response::success($invoices, 'Invoices retrieved', 200, [
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
        ]);
    }

    public function show(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.view');
        $id = $request->getParam('id');
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT i.*, c.name as customer_name, c.code as customer_code, c.email as customer_email,
                                      c.phone as customer_phone, c.address as customer_address, c.tax_number as customer_tax,
                                      so.so_number, del.delivery_number, u.name as creator_name,
                                      vr.voucher_number as receivable_voucher_no,
                                      vc.voucher_number as cost_voucher_no
                               FROM invoices i
                               JOIN customers c ON i.customer_id = c.id
                               LEFT JOIN sales_orders so ON i.so_id = so.id
                               LEFT JOIN deliveries del ON i.delivery_id = del.id
                               LEFT JOIN users u ON i.created_by = u.id
                               LEFT JOIN vouchers vr ON i.receivable_voucher_id = vr.id
                               LEFT JOIN vouchers vc ON i.cost_voucher_id = vc.id
                               WHERE i.id = :id AND i.company_id = :cid");
        $stmt->execute([':id' => $id, ':cid' => $request->getCompanyId()]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            Response::notFound("Invoice not found");
        }

        $itemsStmt = $pdo->prepare("SELECT ii.*, p.code as product_code, p.name as product_name, u.symbol as unit_symbol
                                    FROM invoice_items ii
                                    JOIN products p ON ii.product_id = p.id
                                    LEFT JOIN units u ON p.unit_id = u.id
                                    WHERE ii.invoice_id = :id");
        $itemsStmt->execute([':id' => $id]);
        $invoice['items'] = $itemsStmt->fetchAll();

        Response::success($invoice);
    }

    public function store(Request $request): void
    {
        PermissionMiddleware::require($request, 'sales.invoice');
        $data = Validator::validate($request->body(), [
            'customer_id' => 'required|integer',
            'date'        => 'required',
            'items'       => 'required',
        ]);

        $items = $request->get('items');
        if (!is_array($items) || empty($items)) {
            Response::error("Invoice must have at least one line item.", 422);
        }

        $pdo = Database::getConnection();
        Database::beginTransaction();
        try {
            $companyId = $request->getCompanyId();
            $branchId = $request->getBranchId();
            $customerId = (int)$data['customer_id'];
            $invoiceDate = $data['date'];
            $dueDate = $request->get('due_date') ?: $invoiceDate;
            $soId = $request->get('so_id') ? (int)$request->get('so_id') : null;
            $deliveryId = $request->get('delivery_id') ? (int)$request->get('delivery_id') : null;

            $subtotal = 0.0;
            $taxAmount = 0.0;
            $discountAmount = 0.0;
            $totalCost = 0.0;

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $tax = (float)($item['tax_amount'] ?? 0);
                $disc = (float)($item['discount_amount'] ?? 0);

                $subtotal += ($qty * $price);
                $taxAmount += $tax;
                $discountAmount += $disc;

                // Product cost for COGS voucher
                $pCostStmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = :pid");
                $pCostStmt->execute([':pid' => $item['product_id']]);
                $cPrice = (float)$pCostStmt->fetchColumn();
                $totalCost += ($qty * $cPrice);
            }

            $totalAmount = $subtotal + $taxAmount - $discountAmount;
            if ($totalAmount <= 0) {
                throw new Exception("Invoice net total amount must be greater than zero.");
            }

            $invNum = SequenceHelper::getNextNumber($companyId, 'INV', 'INV');

            $invStmt = $pdo->prepare("INSERT INTO invoices (company_id, branch_id, invoice_number, so_id, delivery_id, 
                                                          customer_id, date, due_date, status, subtotal, tax_amount, 
                                                          discount_amount, total_amount, paid_amount, balance_amount, 
                                                          remarks, created_by) 
                                      VALUES (:cid, :bid, :num, :soid, :delid, :cust, :dt, :due, 'unpaid', :sub, 
                                              :tax, :disc, :tot, 0, :tot2, :rem, :uid)");
            $invStmt->execute([
                ':cid'   => $companyId,
                ':bid'   => $branchId,
                ':num'   => $invNum,
                ':soid'  => $soId,
                ':delid' => $deliveryId,
                ':cust'  => $customerId,
                ':dt'    => $invoiceDate,
                ':due'   => $dueDate,
                ':sub'   => $subtotal,
                ':tax'   => $taxAmount,
                ':disc'  => $discountAmount,
                ':tot'   => $totalAmount,
                ':tot2'  => $totalAmount,
                ':rem'   => $request->get('remarks'),
                ':uid'   => $request->getUserId(),
            ]);
            $invoiceId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("INSERT INTO invoice_items (invoice_id, product_id, quantity, unit_price, 
                                                                tax_rate, tax_amount, discount_amount, total_price) 
                                       VALUES (:invid, :pid, :qty, :price, :trate, :tax, :disc, :tot)");

            foreach ($items as $item) {
                $qty = (float)($item['quantity'] ?? 0);
                $price = (float)($item['unit_price'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? 0);
                $tax = (float)($item['tax_amount'] ?? 0);
                $disc = (float)($item['discount_amount'] ?? 0);
                $lineTotal = ($qty * $price) + $tax - $disc;

                $itemStmt->execute([
                    ':invid' => $invoiceId,
                    ':pid'   => $item['product_id'],
                    ':qty'   => $qty,
                    ':price' => $price,
                    ':trate' => $taxRate,
                    ':tax'   => $tax,
                    ':disc'  => $disc,
                    ':tot'   => $lineTotal,
                ]);
            }

            // 1. Double-Entry Accounting Voucher: Receivable Voucher
            // Debit: Customer Receivable (1030)
            // Credit: Sales Revenue (4010)
            // Credit: Sales Tax Payable (2030) if tax > 0
            // Debit: Sales Discounts (4020) if disc > 0
            $arAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '1030'");
            $arAccStmt->execute([':cid' => $companyId]);
            $arAccountId = (int)($arAccStmt->fetchColumn() ?: 4);

            $salesAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '4010'");
            $salesAccStmt->execute([':cid' => $companyId]);
            $salesAccountId = (int)($salesAccStmt->fetchColumn() ?: 16);

            $taxAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '2030'");
            $taxAccStmt->execute([':cid' => $companyId]);
            $taxAccountId = (int)($taxAccStmt->fetchColumn() ?: 11);

            $discAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '4020'");
            $discAccStmt->execute([':cid' => $companyId]);
            $discAccountId = (int)($discAccStmt->fetchColumn() ?: 17);

            $vrNum = SequenceHelper::getNextNumber($companyId, 'VR', 'VR');
            $totDebits = $totalAmount + $discountAmount;
            $totCredits = $subtotal + $taxAmount;

            $vrStmt = $pdo->prepare("INSERT INTO vouchers (company_id, branch_id, voucher_number, voucher_type, date, 
                                                          reference_type, reference_id, narration, total_debit, total_credit, 
                                                          status, created_by, posted_by, posted_at) 
                                     VALUES (:cid, :bid, :vnum, 'sales', :dt, 'INVOICE', :ref_id, :nar, :deb, :crd, 'posted', :uid, :uid, NOW())");
            $vrStmt->execute([
                ':cid'    => $companyId,
                ':bid'    => $branchId,
                ':vnum'   => $vrNum,
                ':dt'     => $invoiceDate,
                ':ref_id' => $invoiceId,
                ':nar'    => "Receivable for sales invoice #$invNum",
                ':deb'    => $totDebits,
                ':crd'    => $totCredits,
                ':uid'    => $request->getUserId(),
            ]);
            $recVoucherId = (int)$pdo->lastInsertId();

            $vlStmt = $pdo->prepare("INSERT INTO voucher_lines (voucher_id, account_id, party_type, party_id, debit, credit, description) 
                                     VALUES (:vid, :acc, :ptype, :pid, :deb, :crd, :desc)");

            // Debit Customer Receivable
            $vlStmt->execute([
                ':vid'   => $recVoucherId,
                ':acc'   => $arAccountId,
                ':ptype' => 'customer',
                ':pid'   => $customerId,
                ':deb'   => $totalAmount,
                ':crd'   => 0,
                ':desc'  => "Customer receivable for invoice #$invNum",
            ]);

            // Debit Sales Discounts Allowed if applicable
            if ($discountAmount > 0) {
                $vlStmt->execute([
                    ':vid'   => $recVoucherId,
                    ':acc'   => $discAccountId,
                    ':ptype' => 'none',
                    ':pid'   => null,
                    ':deb'   => $discountAmount,
                    ':crd'   => 0,
                    ':desc'  => "Discount on invoice #$invNum",
                ]);
            }

            // Credit Sales Revenue
            $vlStmt->execute([
                ':vid'   => $recVoucherId,
                ':acc'   => $salesAccountId,
                ':ptype' => 'none',
                ':pid'   => null,
                ':deb'   => 0,
                ':crd'   => $subtotal,
                ':desc'  => "Sales revenue from invoice #$invNum",
            ]);

            // Credit Sales Tax Payable if applicable
            if ($taxAmount > 0) {
                $vlStmt->execute([
                    ':vid'   => $recVoucherId,
                    ':acc'   => $taxAccountId,
                    ':ptype' => 'none',
                    ':pid'   => null,
                    ':deb'   => 0,
                    ':crd'   => $taxAmount,
                    ':desc'  => "Sales tax on invoice #$invNum",
                ]);
            }

            // 2. Double-Entry Accounting Voucher: COGS Cost Voucher
            // Debit: Cost of Goods Sold (5010)
            // Credit: Inventory / Stock Asset (1040)
            $cogsAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '5010'");
            $cogsAccStmt->execute([':cid' => $companyId]);
            $cogsAccountId = (int)($cogsAccStmt->fetchColumn() ?: 20);

            $invAccStmt = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE company_id = :cid AND code = '1040'");
            $invAccStmt->execute([':cid' => $companyId]);
            $invAccountId = (int)($invAccStmt->fetchColumn() ?: 5);

            $costVoucherId = null;
            if ($totalCost > 0) {
                $vcNum = SequenceHelper::getNextNumber($companyId, 'VR', 'VR');
                $vcStmt = $pdo->prepare("INSERT INTO vouchers (company_id, branch_id, voucher_number, voucher_type, date, 
                                                              reference_type, reference_id, narration, total_debit, total_credit, 
                                                              status, created_by, posted_by, posted_at) 
                                         VALUES (:cid, :bid, :vnum, 'journal', :dt, 'INVOICE_COST', :ref_id, :nar, :tot, :tot, 'posted', :uid, :uid, NOW())");
                $vcStmt->execute([
                    ':cid'    => $companyId,
                    ':bid'    => $branchId,
                    ':vnum'   => $vcNum,
                    ':dt'     => $invoiceDate,
                    ':ref_id' => $invoiceId,
                    ':nar'    => "Cost of goods sold for invoice #$invNum",
                    ':tot'    => $totalCost,
                    ':uid'    => $request->getUserId(),
                ]);
                $costVoucherId = (int)$pdo->lastInsertId();

                // Debit COGS
                $vlStmt->execute([
                    ':vid'   => $costVoucherId,
                    ':acc'   => $cogsAccountId,
                    ':ptype' => 'none',
                    ':pid'   => null,
                    ':deb'   => $totalCost,
                    ':crd'   => 0,
                    ':desc'  => "COGS expense for invoice #$invNum",
                ]);

                // Credit Inventory Asset
                $vlStmt->execute([
                    ':vid'   => $costVoucherId,
                    ':acc'   => $invAccountId,
                    ':ptype' => 'none',
                    ':pid'   => null,
                    ':deb'   => 0,
                    ':crd'   => $totalCost,
                    ':desc'  => "Inventory deduction cost for invoice #$invNum",
                ]);
            }

            // 3. Update Customer Current Balance (Increases receivable)
            $updCust = $pdo->prepare("UPDATE customers SET current_balance = current_balance + :amt, updated_at = NOW() WHERE id = :cust");
            $updCust->execute([':amt' => $totalAmount, ':cust' => $customerId]);

            // 4. Update Invoice with Voucher IDs
            $updInvV = $pdo->prepare("UPDATE invoices SET receivable_voucher_id = :rvid, cost_voucher_id = :cvid WHERE id = :id");
            $updInvV->execute([
                ':rvid' => $recVoucherId,
                ':cvid' => $costVoucherId,
                ':id'   => $invoiceId,
            ]);

            Database::commit();
            AuditLogger::log($request, 'CREATE_INVOICE', 'Invoice', (string)$invoiceId, null, [
                'invoice_number'        => $invNum,
                'receivable_voucher_id' => $recVoucherId,
                'cost_voucher_id'       => $costVoucherId,
                'total_amount'          => $totalAmount,
            ]);

            Response::created([
                'id'                    => $invoiceId,
                'invoice_number'        => $invNum,
                'receivable_voucher_id' => $recVoucherId,
                'cost_voucher_id'       => $costVoucherId,
                'total_amount'          => $totalAmount,
            ], 'Sales invoice generated, receivable voucher and COGS voucher posted successfully');
        } catch (Exception $e) {
            Database::rollBack();
            Response::error('Failed to generate sales invoice: ' . $e->getMessage(), 500);
        }
    }
}
