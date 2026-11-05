<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Env;
use App\Core\Request;

class CorsMiddleware
{
    public function handle(Request $request): void
    {
        $allowedOrigins = explode(',', Env::get('CORS_ALLOWED_ORIGINS', '*'));
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        $allowOrigin = '*';
        if (in_array('*', $allowedOrigins, true)) {
            $allowOrigin = '*';
        } else {
            foreach ($allowedOrigins as $allowed) {
                $allowed = trim($allowed);
                if ($allowed === $origin) {
                    $allowOrigin = $origin;
                    break;
                }
            }
        }

        header("Access-Control-Allow-Origin: $allowOrigin");
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, Accept, Origin, X-Requested-With, X-Company-Id, X-Branch-Id");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400");

        // Basic Security Headers
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");

        if ($request->getMethod() === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
}
