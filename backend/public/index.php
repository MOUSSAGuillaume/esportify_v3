<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0'); // local http

session_start();

use App\Controller\AuthController;
use App\Controller\AdminEventController;
use App\Controller\EventController;

use App\Repository\UserRepository;
use App\Repository\EventRepository;

use App\Service\AuthService;

use App\Middleware\AuthMiddleware;

use App\Security\Csrf;


$pdo = new PDO(
    "mysql:host=mysql;dbname=esportify;charset=utf8mb4",
    "esportify_user",
    "esportify_pass",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '/register' && $method === 'POST') {
    $controller = new AuthController(
        new AuthService(new UserRepository($pdo))
    );
    $controller->register();
    exit;
}

if ($path === '/login' && $method === 'POST') {
    $controller = new AuthController(
        new AuthService(new UserRepository($pdo))
    );
    $controller->login();
    exit;
}

if ($path === '/logout' && $method === 'POST') {
    $controller = new AuthController(
        new AuthService(new UserRepository($pdo))
    );
    $controller->logout();
    exit;
}

if ($path === '/me' && $method === 'GET') {
    $controller = new AuthController(new AuthService(new UserRepository($pdo)));
    $controller->me();
    exit;
}

if ($path === '/admin/ping' && $method === 'GET') {
    AuthMiddleware::requireRole(['ADMIN']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'message' => 'admin access ok'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/csrf' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['csrfToken' => Csrf::token()], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/events' && $method === 'GET') {
    $controller = new EventController(new EventRepository($pdo));
    $controller->list();
    exit;
}

if ($path === '/events' && $method === 'POST') {
    \App\Middleware\AuthMiddleware::requireRole(['ORGANIZER']);
    $controller = new EventController(new EventRepository($pdo));
    $controller->create();
    exit;
}

// ADMIN: list events by status
if ($path === '/admin/events' && $method === 'GET') {
    \App\Middleware\AuthMiddleware::requireRole(['ADMIN']);
    $controller = new AdminEventController(new EventRepository($pdo));
    $controller->list();
    exit;
}

// ADMIN: validate/reject with dynamic path: /admin/events/{id}/validate|reject
if ($method === 'POST' && preg_match('#^/admin/events/(\d+)/(validate|reject)$#', $path, $m)) {
    \App\Middleware\AuthMiddleware::requireRole(['ADMIN']);

    $id = (int)$m[1];
    $action = $m[2];

    $controller = new AdminEventController(new EventRepository($pdo));

    if ($action === 'validate') {
        $controller->validate($id);
        exit;
    }

    if ($action === 'reject') {
        $controller->reject($id);
        exit;
    }
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['message' => 'API Esportify'], JSON_UNESCAPED_UNICODE);