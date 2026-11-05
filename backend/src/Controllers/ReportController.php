<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\PermissionMiddleware;
use PDO;

class ReportController
{
    public function trialBalance(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.reports');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $asOfDate = $request->getQuery('as_of_date', $request->getQuery('to_date', date('Y-m-d')));

        // Only include posted vouchers up to asOfDate
        $sql = "SELECT coa.id, coa.code, coa.name, coa.type,
                       COALESCE(SUM(vl.debit), 0) as total_debit,
                       COALESCE(SUM(vl.credit), 0) as total_credit
                FROM chart_of_accounts coa
                LEFT JOIN voucher_lines vl ON coa.id = vl.account_id
                LEFT JOIN vouchers v ON vl.voucher_id = v.id AND v.status = 'posted' AND v.date <= :as_of AND v.company_id = :cid1
                WHERE coa.company_id = :cid2 AND coa.is_active = 1
                GROUP BY coa.id, coa.code, coa.name, coa.type
                HAVING total_debit > 0 OR total_credit > 0
                ORDER BY coa.code ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':as_of' => $asOfDate,
            ':cid1'  => $companyId,
            ':cid2'  => $companyId,
        ]);
        $rows = $stmt->fetchAll();

        $grandDebit = 0.0;
        $grandCredit = 0.0;
        $items = [];

        foreach ($rows as $row) {
            $deb = (float)$row['total_debit'];
            $crd = (float)$row['total_credit'];
            $net = $deb - $crd;

            $netDebit = 0.0;
            $netCredit = 0.0;

            if ($net > 0) {
                $netDebit = $net;
            } elseif ($net < 0) {
                $netCredit = abs($net);
            }

            $grandDebit += $netDebit;
            $grandCredit += $netCredit;

            $items[] = [
                'id'             => (int)$row['id'],
                'account_id'     => (int)$row['id'],
                'code'           => $row['code'],
                'account_code'   => $row['code'],
                'name'           => $row['name'],
                'account_name'   => $row['name'],
                'type'           => $row['type'],
                'raw_debit'      => $deb,
                'raw_credit'     => $crd,
                'net_debit'      => $netDebit,
                'net_credit'     => $netCredit,
                'closing_debit'  => $netDebit,
                'closing_credit' => $netCredit,
            ];
        }

        Response::success([
            'as_of_date'   => $asOfDate,
            'items'        => $items,
            'total_debit'  => round($grandDebit, 2),
            'total_credit' => round($grandCredit, 2),
            'is_balanced'  => abs($grandDebit - $grandCredit) < 0.01,
            'difference'   => round(abs($grandDebit - $grandCredit), 2),
        ]);
    }

    public function generalLedger(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.reports');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $accountId = (int)$request->getQuery('account_id', 0);
        $startDate = $request->getQuery('start_date', $request->getQuery('from_date', date('Y-01-01')));
        $endDate = $request->getQuery('end_date', $request->getQuery('to_date', date('Y-m-d')));

        if ($accountId <= 0) {
            Response::error("Please select an account for General Ledger.", 422);
        }

        $accStmt = $pdo->prepare("SELECT * FROM chart_of_accounts WHERE id = :id AND company_id = :cid");
        $accStmt->execute([':id' => $accountId, ':cid' => $companyId]);
        $account = $accStmt->fetch();
        if (!$account) {
            Response::notFound("Account not found");
        }

        // 1. Calculate opening balance before startDate
        $opStmt = $pdo->prepare("SELECT COALESCE(SUM(vl.debit), 0) as op_debit, COALESCE(SUM(vl.credit), 0) as op_credit 
                                FROM voucher_lines vl
                                JOIN vouchers v ON vl.voucher_id = v.id
                                WHERE vl.account_id = :aid AND v.status = 'posted' AND v.date < :sdate AND v.company_id = :cid");
        $opStmt->execute([':aid' => $accountId, ':sdate' => $startDate, ':cid' => $companyId]);
        $opRow = $opStmt->fetch();

        $isDebitNormal = in_array($account['type'], ['asset', 'expense']);
        $opNet = (float)$opRow['op_debit'] - (float)$opRow['op_credit'];
        $openingBalance = $isDebitNormal ? $opNet : -$opNet;

        // 2. Fetch lines between startDate and endDate
        $linesSql = "SELECT vl.*, v.voucher_number, v.voucher_type, v.date, v.narration,
                            CASE 
                              WHEN vl.party_type = 'customer' THEN (SELECT c.name FROM customers c WHERE c.id = vl.party_id)
                              WHEN vl.party_type = 'supplier' THEN (SELECT s.name FROM suppliers s WHERE s.id = vl.party_id)
                              WHEN vl.party_type = 'employee' THEN (SELECT CONCAT(e.first_name, ' ', e.last_name) FROM employees e WHERE e.id = vl.party_id)
                              ELSE NULL 
                            END as party_name
                     FROM voucher_lines vl
                     JOIN vouchers v ON vl.voucher_id = v.id
                     WHERE vl.account_id = :aid AND v.status = 'posted' AND v.date BETWEEN :sdate AND :edate AND v.company_id = :cid
                     ORDER BY v.date ASC, v.id ASC, vl.id ASC";
        $linesStmt = $pdo->prepare($linesSql);
        $linesStmt->execute([
            ':aid'   => $accountId,
            ':sdate' => $startDate,
            ':edate' => $endDate,
            ':cid'   => $companyId,
        ]);
        $rows = $linesStmt->fetchAll();

        $running = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $entries = [];

        foreach ($rows as $r) {
            $deb = (float)$r['debit'];
            $crd = (float)$r['credit'];
            $totalDebit += $deb;
            $totalCredit += $crd;

            if ($isDebitNormal) {
                $running += ($deb - $crd);
            } else {
                $running += ($crd - $deb);
            }

            $r['running_balance'] = $running;
            $r['balance'] = $running;
            $r['reference'] = $r['voucher_number'] ?? '';
            $entries[] = $r;
        }

        Response::success([
            'account'         => $account,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $running,
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'entries'         => $entries,
        ]);
    }

    public function customerLedger(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.reports');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $customerId = (int)$request->getQuery('customer_id', 0);
        $startDate = $request->getQuery('start_date', $request->getQuery('from_date', date('Y-01-01')));
        $endDate = $request->getQuery('end_date', $request->getQuery('to_date', date('Y-m-d')));

        if ($customerId <= 0) {
            Response::error("Please select a customer.", 422);
        }

        $custStmt = $pdo->prepare("SELECT * FROM customers WHERE id = :id AND company_id = :cid");
        $custStmt->execute([':id' => $customerId, ':cid' => $companyId]);
        $customer = $custStmt->fetch();
        if (!$customer) {
            Response::notFound("Customer not found");
        }

        // Opening balance from voucher lines where party_type='customer' and date < startDate
        $opStmt = $pdo->prepare("SELECT COALESCE(SUM(vl.debit), 0) - COALESCE(SUM(vl.credit), 0) as net 
                                FROM voucher_lines vl
                                JOIN vouchers v ON vl.voucher_id = v.id
                                WHERE vl.party_type = 'customer' AND vl.party_id = :cid AND v.status = 'posted' 
                                  AND v.date < :sdate AND v.company_id = :comp_id");
        $opStmt->execute([':cid' => $customerId, ':sdate' => $startDate, ':comp_id' => $companyId]);
        $openingBalance = (float)$opStmt->fetchColumn() + (float)$customer['opening_balance'];

        // Period movements
        $sql = "SELECT vl.debit, vl.credit, vl.description, v.voucher_number, v.voucher_type, v.date, v.narration
                FROM voucher_lines vl
                JOIN vouchers v ON vl.voucher_id = v.id
                WHERE vl.party_type = 'customer' AND vl.party_id = :cid AND v.status = 'posted' 
                  AND v.date BETWEEN :sdate AND :edate AND v.company_id = :comp_id
                ORDER BY v.date ASC, v.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':cid' => $customerId, ':sdate' => $startDate, ':edate' => $endDate, ':comp_id' => $companyId]);
        $rows = $stmt->fetchAll();

        $running = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $entries = [];

        foreach ($rows as $r) {
            $deb = (float)$r['debit'];
            $crd = (float)$r['credit'];
            $totalDebit += $deb;
            $totalCredit += $crd;
            $running += ($deb - $crd);

            $r['running_balance'] = $running;
            $r['balance'] = $running;
            $r['reference'] = $r['voucher_number'] ?? '';
            $entries[] = $r;
        }

        Response::success([
            'customer'        => $customer,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $running,
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'entries'         => $entries,
        ]);
    }

    public function supplierLedger(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.reports');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $supplierId = (int)$request->getQuery('supplier_id', 0);
        $startDate = $request->getQuery('start_date', $request->getQuery('from_date', date('Y-01-01')));
        $endDate = $request->getQuery('end_date', $request->getQuery('to_date', date('Y-m-d')));

        if ($supplierId <= 0) {
            Response::error("Please select a supplier.", 422);
        }

        $supStmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = :id AND company_id = :cid");
        $supStmt->execute([':id' => $supplierId, ':cid' => $companyId]);
        $supplier = $supStmt->fetch();
        if (!$supplier) {
            Response::notFound("Supplier not found");
        }

        // Supplier is credit normal (liability: credit increases payable, debit decreases)
        $opStmt = $pdo->prepare("SELECT COALESCE(SUM(vl.credit), 0) - COALESCE(SUM(vl.debit), 0) as net 
                                FROM voucher_lines vl
                                JOIN vouchers v ON vl.voucher_id = v.id
                                WHERE vl.party_type = 'supplier' AND vl.party_id = :sid AND v.status = 'posted' 
                                  AND v.date < :sdate AND v.company_id = :comp_id");
        $opStmt->execute([':sid' => $supplierId, ':sdate' => $startDate, ':comp_id' => $companyId]);
        $openingBalance = (float)$opStmt->fetchColumn() + (float)$supplier['opening_balance'];

        $sql = "SELECT vl.debit, vl.credit, vl.description, v.voucher_number, v.voucher_type, v.date, v.narration
                FROM voucher_lines vl
                JOIN vouchers v ON vl.voucher_id = v.id
                WHERE vl.party_type = 'supplier' AND vl.party_id = :sid AND v.status = 'posted' 
                  AND v.date BETWEEN :sdate AND :edate AND v.company_id = :comp_id
                ORDER BY v.date ASC, v.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':sid' => $supplierId, ':sdate' => $startDate, ':edate' => $endDate, ':comp_id' => $companyId]);
        $rows = $stmt->fetchAll();

        $running = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $entries = [];

        foreach ($rows as $r) {
            $deb = (float)$r['debit'];
            $crd = (float)$r['credit'];
            $totalDebit += $deb;
            $totalCredit += $crd;
            $running += ($crd - $deb);

            $r['running_balance'] = $running;
            $r['balance'] = $running;
            $r['reference'] = $r['voucher_number'] ?? '';
            $entries[] = $r;
        }

        Response::success([
            'supplier'        => $supplier,
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'opening_balance' => $openingBalance,
            'closing_balance' => $running,
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'entries'         => $entries,
        ]);
    }

    public function profitLoss(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.reports');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $startDate = $request->getQuery('start_date', $request->getQuery('from_date', date('Y-01-01')));
        $endDate = $request->getQuery('end_date', $request->getQuery('to_date', date('Y-m-d')));

        // Income accounts: sum(credit) - sum(debit)
        $incSql = "SELECT coa.id, coa.code, coa.name, 
                          (COALESCE(SUM(vl.credit), 0) - COALESCE(SUM(vl.debit), 0)) as amount
                   FROM chart_of_accounts coa
                   LEFT JOIN voucher_lines vl ON coa.id = vl.account_id
                   LEFT JOIN vouchers v ON vl.voucher_id = v.id AND v.status = 'posted' AND v.date BETWEEN :sdate AND :edate AND v.company_id = :cid1
                   WHERE coa.company_id = :cid2 AND coa.type = 'income' AND coa.is_active = 1
                   GROUP BY coa.id, coa.code, coa.name
                   ORDER BY coa.code ASC";
        $incStmt = $pdo->prepare($incSql);
        $incStmt->execute([':sdate' => $startDate, ':edate' => $endDate, ':cid1' => $companyId, ':cid2' => $companyId]);
        $incomeAccounts = $incStmt->fetchAll();

        $totalRevenue = 0.0;
        foreach ($incomeAccounts as &$acc) {
            $acc['account_name'] = $acc['name'];
            $acc['account_code'] = $acc['code'];
            $totalRevenue += (float)$acc['amount'];
        }
        unset($acc);

        // Expense accounts: sum(debit) - sum(credit)
        $expSql = "SELECT coa.id, coa.code, coa.name, 
                          (COALESCE(SUM(vl.debit), 0) - COALESCE(SUM(vl.credit), 0)) as amount
                   FROM chart_of_accounts coa
                   LEFT JOIN voucher_lines vl ON coa.id = vl.account_id
                   LEFT JOIN vouchers v ON vl.voucher_id = v.id AND v.status = 'posted' AND v.date BETWEEN :sdate AND :edate AND v.company_id = :cid1
                   WHERE coa.company_id = :cid2 AND coa.type = 'expense' AND coa.is_active = 1
                   GROUP BY coa.id, coa.code, coa.name
                   ORDER BY coa.code ASC";
        $expStmt = $pdo->prepare($expSql);
        $expStmt->execute([':sdate' => $startDate, ':edate' => $endDate, ':cid1' => $companyId, ':cid2' => $companyId]);
        $expenseAccounts = $expStmt->fetchAll();

        $cogs = 0.0;
        $operatingExpenses = 0.0;
        $cogsItems = [];
        $operatingItems = [];

        foreach ($expenseAccounts as $acc) {
            $amt = (float)$acc['amount'];
            $acc['account_name'] = $acc['name'];
            $acc['account_code'] = $acc['code'];
            if ($acc['code'] === '5010') {
                $cogs += $amt;
                $cogsItems[] = $acc;
            } else {
                $operatingExpenses += $amt;
                $operatingItems[] = $acc;
            }
        }

        $grossProfit = $totalRevenue - $cogs;
        $netProfit = $grossProfit - $operatingExpenses;
        $allExpenses = array_merge($cogsItems, $operatingItems);

        Response::success([
            'start_date'         => $startDate,
            'end_date'           => $endDate,
            'revenue_accounts'   => $incomeAccounts,
            'incomes'            => $incomeAccounts,
            'total_revenue'      => $totalRevenue,
            'total_income'       => $totalRevenue,
            'cogs_items'         => $cogsItems,
            'total_cogs'         => $cogs,
            'gross_profit'       => $grossProfit,
            'operating_expenses' => $operatingItems,
            'expenses'           => $allExpenses,
            'total_operating'    => $operatingExpenses,
            'total_expenses'     => round($cogs + $operatingExpenses, 2),
            'net_profit'         => $netProfit,
        ]);
    }

    public function balanceSheet(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.reports');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $asOfDate = $request->getQuery('as_of_date', $request->getQuery('to_date', date('Y-m-d')));

        // All accounts balances as of date
        $sql = "SELECT coa.id, coa.code, coa.name, coa.type,
                       COALESCE(SUM(vl.debit), 0) as debits,
                       COALESCE(SUM(vl.credit), 0) as credits
                FROM chart_of_accounts coa
                LEFT JOIN voucher_lines vl ON coa.id = vl.account_id
                LEFT JOIN vouchers v ON vl.voucher_id = v.id AND v.status = 'posted' AND v.date <= :as_of AND v.company_id = :cid1
                WHERE coa.company_id = :cid2 AND coa.is_active = 1
                GROUP BY coa.id, coa.code, coa.name, coa.type
                ORDER BY coa.code ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':as_of' => $asOfDate, ':cid1' => $companyId, ':cid2' => $companyId]);
        $all = $stmt->fetchAll();

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;

        $totalIncome = 0.0;
        $totalExpense = 0.0;

        foreach ($all as $acc) {
            $deb = (float)$acc['debits'];
            $crd = (float)$acc['credits'];

            $acc['account_name'] = $acc['name'];
            $acc['account_code'] = $acc['code'];

            if ($acc['type'] === 'asset') {
                $bal = $deb - $crd;
                $acc['balance'] = $bal;
                $acc['amount'] = $bal;
                $assets[] = $acc;
                $totalAssets += $bal;
            } elseif ($acc['type'] === 'liability') {
                $bal = $crd - $deb;
                $acc['balance'] = $bal;
                $acc['amount'] = $bal;
                $liabilities[] = $acc;
                $totalLiabilities += $bal;
            } elseif ($acc['type'] === 'equity') {
                $bal = $crd - $deb;
                $acc['balance'] = $bal;
                $acc['amount'] = $bal;
                $equity[] = $acc;
                $totalEquity += $bal;
            } elseif ($acc['type'] === 'income') {
                $totalIncome += ($crd - $deb);
            } elseif ($acc['type'] === 'expense') {
                $totalExpense += ($deb - $crd);
            }
        }

        $retainedCurrentPeriod = $totalIncome - $totalExpense;
        $totalEquityWithCurrent = $totalEquity + $retainedCurrentPeriod;
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityWithCurrent;

        Response::success([
            'as_of_date'                   => $asOfDate,
            'assets'                       => $assets,
            'total_assets'                 => round($totalAssets, 2),
            'liabilities'                  => $liabilities,
            'total_liabilities'            => round($totalLiabilities, 2),
            'equity'                       => $equity,
            'retained_earnings_current'    => round($retainedCurrentPeriod, 2),
            'total_equity'                 => round($totalEquityWithCurrent, 2),
            'total_liabilities_and_equity' => round($totalLiabilitiesAndEquity, 2),
            'is_balanced'                  => abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01,
            'difference'                   => round(abs($totalAssets - $totalLiabilitiesAndEquity), 2),
        ]);
    }

    public function cashBankBook(Request $request): void
    {
        PermissionMiddleware::require($request, 'accounts.reports');
        $pdo = Database::getConnection();
        $companyId = $request->getCompanyId();

        $startDate = $request->getQuery('start_date', $request->getQuery('from_date', date('Y-01-01')));
        $endDate = $request->getQuery('end_date', $request->getQuery('to_date', date('Y-m-d')));

        // Cash & Bank accounts: code 1010, 1020
        $accSql = "SELECT id, code, name FROM chart_of_accounts 
                   WHERE company_id = :cid AND code IN ('1010', '1020', '1011', '1012') AND is_active = 1";
        $accStmt = $pdo->prepare($accSql);
        $accStmt->execute([':cid' => $companyId]);
        $cashBankAccounts = $accStmt->fetchAll();

        $accIds = array_column($cashBankAccounts, 'id');
        if (empty($accIds)) {
            Response::success(['entries' => [], 'opening_balance' => 0, 'closing_balance' => 0]);
        }

        $inClause = implode(',', array_map('intval', $accIds));

        // Opening balance
        $opSql = "SELECT COALESCE(SUM(vl.debit), 0) - COALESCE(SUM(vl.credit), 0) as net 
                  FROM voucher_lines vl
                  JOIN vouchers v ON vl.voucher_id = v.id
                  WHERE vl.account_id IN ($inClause) AND v.status = 'posted' AND v.date < :sdate AND v.company_id = :cid";
        $opStmt = $pdo->prepare($opSql);
        $opStmt->execute([':sdate' => $startDate, ':cid' => $companyId]);
        $openingBalance = (float)$opStmt->fetchColumn();

        // Period movements
        $sql = "SELECT vl.*, coa.name as account_name, coa.code as account_code,
                       v.voucher_number, v.voucher_type, v.date, v.narration,
                       CASE 
                         WHEN vl.party_type = 'customer' THEN (SELECT c.name FROM customers c WHERE c.id = vl.party_id)
                         WHEN vl.party_type = 'supplier' THEN (SELECT s.name FROM suppliers s WHERE s.id = vl.party_id)
                         ELSE NULL 
                       END as party_name
                FROM voucher_lines vl
                JOIN chart_of_accounts coa ON vl.account_id = coa.id
                JOIN vouchers v ON vl.voucher_id = v.id
                WHERE vl.account_id IN ($inClause) AND v.status = 'posted' 
                  AND v.date BETWEEN :sdate AND :edate AND v.company_id = :cid
                ORDER BY v.date ASC, v.id ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':sdate' => $startDate, ':edate' => $endDate, ':cid' => $companyId]);
        $rows = $stmt->fetchAll();

        $running = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $entries = [];

        foreach ($rows as $r) {
            $deb = (float)$r['debit'];
            $crd = (float)$r['credit'];
            $totalDebit += $deb;
            $totalCredit += $crd;
            $running += ($deb - $crd);

            $r['running_balance'] = $running;
            $r['balance'] = $running;
            $r['reference'] = $r['voucher_number'] ?? '';
            $entries[] = $r;
        }

        Response::success([
            'start_date'      => $startDate,
            'end_date'        => $endDate,
            'accounts'        => $cashBankAccounts,
            'opening_balance' => $openingBalance,
            'closing_balance' => $running,
            'total_debit'     => $totalDebit,
            'total_credit'    => $totalCredit,
            'entries'         => $entries,
        ]);
    }
}
