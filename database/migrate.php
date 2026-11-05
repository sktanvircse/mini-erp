<?php
declare(strict_types=1);

// Mini ERP CLI Database Migrator & Seeder
echo "=== Mini ERP Database Migrator ===\n";

$envFile = __DIR__ . '/../backend/.env';
if (!file_exists($envFile)) {
    die("Error: .env file not found at $envFile\n");
}

$env = parse_ini_file($envFile);
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = (int)($env['DB_PORT'] ?? 3306);
$dbName = $env['DB_DATABASE'] ?? 'mini_erp';
$user = $env['DB_USERNAME'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';
$charset = $env['DB_CHARSET'] ?? 'utf8mb4';

try {
    // 1. Connect without db to ensure database exists
    $pdoInit = new PDO("mysql:host=$host;port=$port;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "[OK] Database `$dbName` verified.\n";

    // 2. Connect with db
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbName;charset=$charset", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);

    // 3. Execute Schema
    echo "Running schema.sql...\n";
    $schemaSql = file_get_contents(__DIR__ . '/schema.sql');
    $pdo->exec($schemaSql);
    // Drain any multi statement results
    while ($pdo->query("SELECT 1")->nextRowset()) {}
    echo "[OK] Schema imported successfully.\n";

    // 4. Execute Seed
    echo "Running seed.sql...\n";
    $seedSql = file_get_contents(__DIR__ . '/seed.sql');
    $pdo->exec($seedSql);
    while ($pdo->query("SELECT 1")->nextRowset()) {}
    echo "[OK] Seed imported successfully.\n";

    // 5. Verify Tables
    $stmt = $pdo->query("SHOW TABLES;");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "[OK] Verification: " . count($tables) . " tables created:\n";
    foreach ($tables as $t) {
        echo "  - $t\n";
    }

    echo "\n=== Migration & Seeding Completed Successfully! ===\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
