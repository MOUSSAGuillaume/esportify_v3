<?php

declare(strict_types=1);

use App\Middleware\RateLimitMiddleware;

require __DIR__ . '/../vendor/autoload.php';

/*
 * ============================================================
 * 1) Environnement (dev/prod)
 * ============================================================
 */
$isDev = true; // mets false en prod

ini_set('display_errors', $isDev ? '1' : '0');
ini_set('display_startup_errors', $isDev ? '1' : '0');
error_reporting($isDev ? E_ALL : 0);

/*
 * ============================================================
 * 2) Sécurité session
 * ============================================================
 */
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0'); // 1 en HTTPS
session_start();

/*
 * ============================================================
 * 3) CORS (dev)
 * - IMPORTANT: adapte la liste à ton front
 * ============================================================
 */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'http://192.168.1.128:5500',
    'http://localhost:5500',
    'http://localhost:8080', // si tu testes depuis le même host
];

if ($origin && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, X-CSRF-TOKEN');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
}

// Preflight
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/*
 * ============================================================
 * 4) Helpers JSON
 * ============================================================
 */
header('Content-Type: application/json; charset=utf-8');

$sendJson = static function (array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

/*
 * ============================================================
 * 5) Gestion d'erreurs -> JSON (propre)
 * ============================================================
 */
set_exception_handler(static function (Throwable $e) use ($sendJson, $isDev): void {
    $payload = [
        'error' => 'Erreur serveur',
    ];

    if ($isDev) {
        $payload['details'] = $e->getMessage();
        $payload['type'] = get_class($e);
        // $payload['trace'] = $e->getTraceAsString(); // optionnel
    }

    $sendJson($payload, 500);
});

set_error_handler(static function (int $severity, string $message, string $file, int $line) use ($sendJson, $isDev): bool {
    $payload = ['error' => 'Erreur serveur'];

    if ($isDev) {
        $payload['details'] = $message . " ($file:$line)";
        $payload['severity'] = $severity;
    }

    $sendJson($payload, 500);
    return true;
});

/*
 * ============================================================
 * 6) Connexion DB (PDO)
 * ============================================================
 */
$pdo = new PDO(
    "mysql:host=mysql;dbname=esportify;charset=utf8mb4",
    "esportify_user",
    "esportify_pass",
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

/*
 * ============================================================
 * 7) Route parsing
 * ============================================================
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/*
 * ============================================================
 * 8) Rate limit global (par IP + route)
 * - login/register: 10 req / 60s
 * - POST/DELETE (écriture): 60 req / 60s
 * - GET (lecture): 120 req / 60s
 * ============================================================
 */
if ($path === '/login' || $path === '/register') {
    RateLimitMiddleware::throttle(RateLimitMiddleware::key($method, $path), 10, 60);
} elseif ($method === 'POST' || $method === 'PUT' || $method === 'DELETE') {
    RateLimitMiddleware::throttle(RateLimitMiddleware::key($method, $path), 60, 60);
} else {
    RateLimitMiddleware::throttle(RateLimitMiddleware::key($method, $path), 120, 60);
}

/*
 * ============================================================
 * 9) Routes
 * - Ton routes.php doit utiliser $pdo, $path, $method
 * - IMPORTANT: routes.php doit "exit" quand une route est matchée
 * ============================================================
 */
require __DIR__ . '/../config/routes.php';

/*
 * ============================================================
 * 10) Fallback 404 propre
 * ============================================================
 */
$sendJson(['error' => 'Route introuvable', 'path' => $path], 404);
