<?php
declare(strict_types=1);

// Mini ERP API Entry Point

// Global error & exception handling
error_reporting(E_ALL);

$logFile = __DIR__ . '/../logs/app.log';

set_error_handler(function ($severity, $message, $file, $line) use ($logFile) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $logMsg = date('[Y-m-d H:i:s]') . " PHP Error: [$severity] $message in $file on line $line\n";
    file_put_contents($logFile, $logMsg, FILE_APPEND);
    return true;
});

set_exception_handler(function (Throwable $e) use ($logFile) {
    $logMsg = date('[Y-m-d H:i:s]') . " Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    file_put_contents($logFile, $logMsg, FILE_APPEND);

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    
    $isLocal = ($_ENV['APP_ENV'] ?? 'local') === 'local';
    echo json_encode([
        'success' => false,
        'message' => 'Internal Server Error',
        'errors'  => $isLocal ? ['exception' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

require_once __DIR__ . '/../src/autoload.php';

use App\Core\Env;
use App\Core\Request;
use App\Core\Router;
use App\Middleware\CorsMiddleware;

// 1. Load Environment Variables
Env::load(__DIR__ . '/../.env');

// 2. Initialize Request
$request = new Request();

// 3. Handle CORS for all requests
$cors = new CorsMiddleware();
$cors->handle($request);

// 4. Initialize Router
$router = new Router();

// 5. Load Routes
$routesLoader = require_once __DIR__ . '/../src/routes.php';
$routesLoader($router);

// 6. Dispatch Request
$router->dispatch($request);
