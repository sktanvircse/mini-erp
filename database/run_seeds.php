<?php
/**
 * Run all seed SQL files against the Mini ERP database.
 * Usage: php run_seeds.php [--reset]
 *   --reset  Drops and re-creates all tables from schema.sql first
 */

$dbHost = '127.0.0.1';
$dbPort = 3306;
$dbName = 'mini_erp';
$dbUser = 'root';
$dbPass = '';

$baseDir = __DIR__;

// Check for --reset flag
$reset = in_array('--reset', $argv ?? []);

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser, $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Connected to database: {$dbName}\n\n";

    if ($reset) {
        echo "=== RESETTING DATABASE (schema.sql) ===\n";
        $schemaSql = file_get_contents($baseDir . '/schema.sql');
        $pdo->exec($schemaSql);
        echo "✓ Schema recreated successfully\n\n";
    }

    // Run base seed
    echo "=== Running seed.sql (master data) ===\n";
    $seedSql = file_get_contents($baseDir . '/seed.sql');
    $pdo->exec($seedSql);
    echo "✓ Base seed data loaded\n\n";

    // Run transaction seed
    echo "=== Running seed_transactions.sql (transaction data) ===\n";
    $txnSql = file_get_contents($baseDir . '/seed_transactions.sql');
    $pdo->exec($txnSql);
    echo "✓ Transaction seed data loaded\n\n";

    // Verify all tables have data
    echo "=== VERIFICATION: Row counts per table ===\n";
    $tables = [
        'companies', 'branches', 'roles', 'permissions', 'role_permissions', 'users',
        'settings', 'categories', 'units', 'warehouses', 'products',
        'suppliers', 'customers',
        'departments', 'designations', 'employees',
        'chart_of_accounts', 'financial_periods',
        'purchase_requisitions', 'purchase_requisition_items',
        'purchase_orders', 'purchase_order_items',
        'goods_receipts', 'goods_receipt_items',
        'purchase_returns', 'purchase_return_items',
        'sales_orders', 'sales_order_items',
        'deliveries', 'delivery_items',
        'invoices', 'invoice_items',
        'money_receipts', 'money_receipt_invoices',
        'sales_returns', 'sales_return_items',
        'stock_transfers', 'stock_transfer_items',
        'stock_balances', 'stock_ledger',
        'vouchers', 'voucher_lines',
        'attendance', 'leave_requests',
        'payroll_batches', 'payroll_items',
        'audit_logs', 'document_sequences'
    ];

    $allPopulated = true;
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
            $icon = $count > 0 ? '✓' : '✗';
            if ($count == 0) $allPopulated = false;
            printf("  %s %-32s %d rows\n", $icon, $table, $count);
        } catch (Exception $e) {
            printf("  ✗ %-32s ERROR: %s\n", $table, $e->getMessage());
            $allPopulated = false;
        }
    }

    echo "\n";
    if ($allPopulated) {
        echo "═══════════════════════════════════════════\n";
        echo "  ✓ ALL TABLES POPULATED SUCCESSFULLY!    \n";
        echo "═══════════════════════════════════════════\n";
    } else {
        echo "⚠ Some tables may be empty - check above.\n";
    }

    // Verify double-entry balance
    echo "\n=== ACCOUNTING VERIFICATION ===\n";
    $result = $pdo->query("
        SELECT v.voucher_number, v.total_debit, v.total_credit,
               ROUND(SUM(vl.debit),4) as sum_debit, ROUND(SUM(vl.credit),4) as sum_credit,
               CASE WHEN ROUND(SUM(vl.debit),4) = ROUND(SUM(vl.credit),4) THEN 'BALANCED' ELSE 'UNBALANCED' END as status
        FROM vouchers v JOIN voucher_lines vl ON vl.voucher_id = v.id
        GROUP BY v.id ORDER BY v.id
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($result as $row) {
        printf("  %s  %-20s  DR: %12.4f  CR: %12.4f\n",
            $row['status'] === 'BALANCED' ? '✓' : '✗',
            $row['voucher_number'], $row['sum_debit'], $row['sum_credit']
        );
    }

    echo "\nDone!\n";

} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    exit(1);
}
