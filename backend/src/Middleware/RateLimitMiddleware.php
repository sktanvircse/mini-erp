<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;

class RateLimitMiddleware
{
    public function handle(Request $request): void
    {
        $maxAttempts = (int)Env::get('RATE_LIMIT_LOGIN_MAX', 15);
        $decaySeconds = (int)Env::get('RATE_LIMIT_LOGIN_DECAY', 60);

        $ip = $request->getIp();
        $cacheFile = sys_get_temp_dir() . '/erp_rate_limit_' . md5($ip) . '.json';

        $now = time();
        $data = ['count' => 0, 'first_attempt' => $now];

        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            $parsed = json_decode($content, true);
            if (is_array($parsed)) {
                if (($now - ($parsed['first_attempt'] ?? $now)) < $decaySeconds) {
                    $data = $parsed;
                }
            }
        }

        $data['count'] = ($data['count'] ?? 0) + 1;
        if (!isset($data['first_attempt'])) {
            $data['first_attempt'] = $now;
        }

        file_put_contents($cacheFile, json_encode($data));

        if ($data['count'] > $maxAttempts) {
            $retryAfter = $decaySeconds - ($now - $data['first_attempt']);
            header("Retry-After: $retryAfter");
            Response::error("Too many login attempts. Please try again in $retryAfter seconds.", 429);
        }
    }
}
