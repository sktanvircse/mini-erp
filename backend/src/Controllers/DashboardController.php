<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

class DashboardController
{
    public function summary(Request $request): void
    {
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        // 1. Today sales
        $todayStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM invoices 
                                    WHERE company_id = :cid AND date = CURRENT_DATE() AND status != 'cancelled'");
        $todayStmt->execute([':cid' => $companyId]);
        $todaySales = (float)$todayStmt->fetchColumn();

        // 2. Month sales
        $monthStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM invoices 
                                    WHERE company_id = :cid AND YEAR(date) = YEAR(CURRENT_DATE()) 
                                      AND MONTH(date) = MONTH(CURRENT_DATE()) AND status != 'cancelled'");
        $monthStmt->execute([':cid' => $companyId]);
        $monthSales = (float)$monthStmt->fetchColumn();

        // 3. Receivables (total unpaid & partially paid invoice balances)
        $recStmt = $pdo->prepare("SELECT COALESCE(SUM(balance_amount), 0) FROM invoices 
                                  WHERE company_id = :cid AND status IN ('unpaid', 'partially_paid')");
        $recStmt->execute([':cid' => $companyId]);
        $receivables = (float)$recStmt->fetchColumn();

        // 4. Payables (from suppliers current_balance)
        $payStmt = $pdo->prepare("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers 
                                  WHERE company_id = :cid AND is_active = 1 AND deleted_at IS NULL");
        $payStmt->execute([':cid' => $companyId]);
        $payables = (float)$payStmt->fetchColumn();

        // 5. Low stock count
        $lowStockStmt = $pdo->prepare("SELECT COUNT(*) FROM products p
                                       WHERE p.company_id = :cid AND p.deleted_at IS NULL AND p.is_active = 1
                                         AND COALESCE((SELECT SUM(sb.quantity) FROM stock_balances sb WHERE sb.product_id = p.id AND sb.company_id = :cid2), 0) <= p.reorder_level");
        $lowStockStmt->execute([':cid' => $companyId, ':cid2' => $companyId]);
        $lowStockCount = (int)$lowStockStmt->fetchColumn();

        // 6. Cash and Bank Balance
        $cashBankStmt = $pdo->prepare("SELECT COALESCE(SUM(vl.debit), 0) - COALESCE(SUM(vl.credit), 0) as balance
                                       FROM voucher_lines vl
                                       JOIN vouchers v ON vl.voucher_id = v.id
                                       JOIN chart_of_accounts coa ON vl.account_id = coa.id
                                       WHERE v.company_id = :cid AND v.status = 'posted' 
                                         AND coa.code IN ('1010', '1020', '1011', '1012')");
        $cashBankStmt->execute([':cid' => $companyId]);
        $cashBalance = (float)$cashBankStmt->fetchColumn();

        // 7. Top selling products
        $topProdStmt = $pdo->prepare("SELECT p.name, p.code, u.symbol as unit,
                                             SUM(ii.quantity) as total_qty,
                                             SUM(ii.total_price) as total_revenue
                                      FROM invoice_items ii
                                      JOIN products p ON ii.product_id = p.id
                                      JOIN invoices i ON ii.invoice_id = i.id
                                      LEFT JOIN units u ON p.unit_id = u.id
                                      WHERE i.company_id = :cid AND i.status != 'cancelled'
                                      GROUP BY p.id
                                      ORDER BY total_revenue DESC
                                      LIMIT 5");
        $topProdStmt->execute([':cid' => $companyId]);
        $topProducts = $topProdStmt->fetchAll();

        // 8. Sales by month (last 6 months)
        $salesByMonthStmt = $pdo->prepare("SELECT DATE_FORMAT(date, '%Y-%m') as ym, 
                                                  DATE_FORMAT(date, '%b %Y') as month_label,
                                                  COALESCE(SUM(total_amount), 0) as total_sales
                                           FROM invoices
                                           WHERE company_id = :cid AND status != 'cancelled'
                                             AND date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
                                           GROUP BY ym, month_label
                                           ORDER BY ym ASC");
        $salesByMonthStmt->execute([':cid' => $companyId]);
        $salesByMonth = $salesByMonthStmt->fetchAll();

        // 9. Recent activities (audit logs)
        $recentAuditStmt = $pdo->prepare("SELECT a.action, a.module, a.record_id, a.created_at, u.name as user_name
                                          FROM audit_logs a
                                          LEFT JOIN users u ON a.user_id = u.id
                                          WHERE a.company_id = :cid
                                          ORDER BY a.id DESC LIMIT 8");
        $recentAuditStmt->execute([':cid' => $companyId]);
        $recentActivities = $recentAuditStmt->fetchAll();

        Response::success([
            'today_sales'       => round($todaySales, 2),
            'month_sales'       => round($monthSales, 2),
            'receivables'       => round($receivables, 2),
            'payables'          => round($payables, 2),
            'low_stock_count'   => $lowStockCount,
            'cash_balance'      => round($cashBalance, 2),
            'top_products'      => $topProducts,
            'sales_by_month'    => $salesByMonth,
            'recent_activities' => $recentActivities,
        ]);
    }
}
