<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dbUrl = Env::get('DATABASE_URL') ?: Env::get('MYSQL_URL');
            if ($dbUrl && str_starts_with($dbUrl, 'mysql://')) {
                $parts = parse_url($dbUrl);
                $host = $parts['host'] ?? '127.0.0.1';
                $port = (int)($parts['port'] ?? 3306);
                $user = $parts['user'] ?? 'root';
                $pass = $parts['pass'] ?? '';
                $db = ltrim($parts['path'] ?? 'mini_erp', '/');
            } else {
                $host = Env::get('DB_HOST') ?: Env::get('MYSQLHOST', '127.0.0.1');
                $port = (int)(Env::get('DB_PORT') ?: Env::get('MYSQLPORT', 3306));
                $db = Env::get('DB_DATABASE') ?: Env::get('MYSQLDATABASE', 'mini_erp');
                $user = Env::get('DB_USERNAME') ?: Env::get('MYSQLUSER', 'root');
                $pass = Env::get('DB_PASSWORD') !== null ? Env::get('DB_PASSWORD') : (Env::get('MYSQLPASSWORD') ?? '');
            }
            $charset = Env::get('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new RuntimeException("Database connection error: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function beginTransaction(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? true : $pdo->beginTransaction();
    }

    public static function commit(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? $pdo->commit() : true;
    }

    public static function rollBack(): bool
    {
        $pdo = self::getConnection();
        return $pdo->inTransaction() ? $pdo->rollBack() : true;
    }
}
