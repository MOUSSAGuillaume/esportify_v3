<?php
declare(strict_types=1);

namespace App\Middleware;

final class RateLimitMiddleware
{
    public static function throttle(string $key, int $max, int $windowSec): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION['_rl'] ??= [];

        $now = time();
        $bucket = $_SESSION['_rl'][$key] ?? null;

        if (!is_array($bucket) || !isset($bucket['reset'], $bucket['count']) || (int)$bucket['reset'] <= $now) {
            $bucket = [
                'reset' => $now + $windowSec,
                'count' => 0,
            ];
        }

        $bucket['count']++;
        $_SESSION['_rl'][$key] = $bucket;

        $remaining = max(0, $max - (int)$bucket['count']);
        $resetTs   = (int)$bucket['reset'];
        $retryAfter = max(1, $resetTs - $now);

        header('X-RateLimit-Limit: ' . $max);
        header('X-RateLimit-Remaining: ' . $remaining);
        header('X-RateLimit-Reset: ' . $resetTs);

        if ((int)$bucket['count'] > $max) {
            http_response_code(429);
            header('Retry-After: ' . $retryAfter); // ✅ standard
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'error' => 'Trop de requêtes, réessaie plus tard',
                'retry_after' => $retryAfter,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public static function key(string $method, string $path): string
    {
        $ip = self::clientIp();

        // ✅ Normalise les ids numériques pour éviter contournement (/events/1/... vs /events/999/...)
        $normPath = preg_replace('#/\d+#', '/:id', $path) ?? $path;

        return $ip . '|' . strtoupper($method) . '|' . $normPath;
    }

    private static function clientIp(): string
    {
        // ✅ Attention: X-Forwarded-For est spoofable si tu n'es pas derrière un proxy contrôlé.
        // En local Docker/nginx, c’est OK si tu configures nginx pour le set proprement.
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($xff) && $xff !== '') {
            // prend le premier (client original)
            $parts = explode(',', $xff);
            $candidate = trim($parts[0]);

            // validation basique IP
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) ? $ip : 'unknown';
    }
}