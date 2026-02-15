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
use App\Controller\EventRegistrationController;
use App\Controller\EventLifecycleController;
use App\Controller\EventResultController;
use App\Controller\MeController;
use App\Controller\ResultController;
use App\Controller\ChatController;


use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use App\Repository\ResultRepository;
use App\Repository\ChatRepository;

use App\Service\AuthService;

use App\Middleware\AuthMiddleware;

use App\Security\Csrf;

use App\Service\MongoClientFactory;

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

if ($method === 'POST' && preg_match('#^/events/(\d+)/register$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER']);

    $eventId = (int)$m[1];

    $controller = new EventRegistrationController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    );
    $controller->register($eventId);
    exit;
}

if ($method === 'GET' && preg_match('#^/events/(\d+)/registrations$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);

    $eventId = (int)$m[1];

    $controller = new EventRegistrationController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    );
    $controller->list($eventId);
    exit;
}

if ($method === 'POST' && preg_match('#^/events/(\d+)/registrations/(\d+)/refuse$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);

    $eventId = (int)$m[1];
    $userId  = (int)$m[2];

    $controller = new EventRegistrationController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    );
    $controller->refuse($eventId, $userId);
    exit;
}

if ($method === 'POST' && preg_match('#^/events/(\d+)/unregister$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER']);

    $eventId = (int)$m[1];

    $controller = new EventRegistrationController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    );
    $controller->unregister($eventId);
    exit;
}

if ($method === 'POST' && preg_match('#^/events/(\d+)/start$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER']);
    $eventId = (int)$m[1];

    $controller = new EventLifecycleController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    );
    $controller->start($eventId);
    exit;
}

if ($method === 'GET' && preg_match('#^/events/(\d+)/join$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER']);
    $eventId = (int)$m[1];

    $controller = new EventLifecycleController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    );
    $controller->joinStatus($eventId);
    exit;
}

if ($method === 'POST' && preg_match('#^/events/(\d+)/finish$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER']);

    $eventId = (int)$m[1];

    $controller = new EventResultController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo),
        new ResultRepository($pdo)
    );
    $controller->finish($eventId);
    exit;
}

if ($method === 'GET' && preg_match('#^/events/(\d+)/standings$#', $path, $m)) {
    $eventId = (int)$m[1];

    $controller = new EventResultController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo),
        new ResultRepository($pdo)
    );
    $controller->standings($eventId);
    exit;
}

// GET /me/results
if ($path === '/me/results' && $method === 'GET') {
    AuthMiddleware::requireLogin();

    $meId = (int)($_SESSION['user']['id'] ?? 0);
    $controller = new ResultController(new ResultRepository($pdo));
    $controller->myResults($meId);
    exit;
}

// GET /me/stats
if ($path === '/me/stats' && $method === 'GET') {
    AuthMiddleware::requireLogin();

    $controller = new MeController(new ResultRepository($pdo));
    $controller->stats();
    exit;
}

// GET /leaderboard
if ($path === '/leaderboard' && $method === 'GET') {
    $controller = new ResultController(new ResultRepository($pdo));
    $controller->leaderboard();
    exit;
}

// CHAT: list messages
if ($method === 'GET' && preg_match('#^/events/(\d+)/chat$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER','ORGANIZER','ADMIN']);

    $eventId = (int)$m[1];

    $controller = new ChatController(
        new ChatRepository(MongoClientFactory::db()),
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    );
    $controller->list($eventId);
    exit;
}

// CHAT: post message
if ($method === 'POST' && preg_match('#^/events/(\d+)/chat$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER', 'ORGANIZER', 'ADMIN']);

    $eventId = (int)$m[1];

    $controller = new ChatController(
        new ChatRepository(MongoClientFactory::db()),
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    );
    $controller->post($eventId);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['message' => 'API Esportify'], JSON_UNESCAPED_UNICODE);