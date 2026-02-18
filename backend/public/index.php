<?php
declare(strict_types=1);
use App\Middleware\RateLimitMiddleware;
require __DIR__ . '/../vendor/autoload.php';


ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0'); // local http
session_start();

header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO(
    "mysql:host=mysql;dbname=esportify;charset=utf8mb4",
    "esportify_user",
    "esportify_pass",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ✅ Rate limit global (par IP + route)
// règles:
// - login/register: 10 req / 60s
// - endpoints "écriture" (POST): 60 req / 60s
// - endpoints lecture (GET): 120 req / 60s
if ($path === '/login' || $path === '/register') {
    RateLimitMiddleware::throttle(RateLimitMiddleware::key($method, $path), 10, 60);
} elseif ($method === 'POST') {
    RateLimitMiddleware::throttle(RateLimitMiddleware::key($method, $path), 60, 60);
} else {
    RateLimitMiddleware::throttle(RateLimitMiddleware::key($method, $path), 120, 60);
}

// ✅ Toutes les routes
require __DIR__ . '/../config/routes.php';

// ✅ Fallback 404 propre
http_response_code(404);
echo json_encode(['error' => 'Route introuvable'], JSON_UNESCAPED_UNICODE);