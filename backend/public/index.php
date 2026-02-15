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
use App\Controller\UserController;
use App\Controller\HealthController;

use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use App\Repository\ResultRepository;
use App\Repository\ChatRepository;

use App\Service\AuthService;
use App\Middleware\AuthMiddleware;
use App\Security\Csrf;
use App\Service\MongoClientFactory;

header('Content-Type: application/json; charset=utf-8');

$pdo = new PDO(
    "mysql:host=mysql;dbname=esportify;charset=utf8mb4",
    "esportify_user",
    "esportify_pass",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/*AUTH*/
if ($path === '/register' && $method === 'POST') {
    (new AuthController(new AuthService(new UserRepository($pdo))))->register();
    exit;
}

if ($path === '/login' && $method === 'POST') {
    (new AuthController(new AuthService(new UserRepository($pdo))))->login();
    exit;
}

if ($path === '/logout' && $method === 'POST') {
    (new AuthController(new AuthService(new UserRepository($pdo))))->logout();
    exit;
}

if ($path === '/me' && $method === 'GET') {
    (new AuthController(new AuthService(new UserRepository($pdo))))->me();
    exit;
}

/*CSRF*/
if ($path === '/csrf' && $method === 'GET') {
    echo json_encode(['csrfToken' => Csrf::token()], JSON_UNESCAPED_UNICODE);
    exit;
}

/*ADMIN*/
if ($path === '/admin/ping' && $method === 'GET') {
    AuthMiddleware::requireRole(['ADMIN']);
    echo json_encode(['ok' => true, 'message' => 'admin access ok'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/admin/events' && $method === 'GET') {
    AuthMiddleware::requireRole(['ADMIN']);
    (new AdminEventController(new EventRepository($pdo)))->list();
    exit;
}

if ($method === 'POST' && preg_match('#^/admin/events/(\d+)/(validate|reject)$#', $path, $m)) {
    AuthMiddleware::requireRole(['ADMIN']);

    $id = (int)$m[1];
    $action = $m[2];

    $controller = new AdminEventController(new EventRepository($pdo));
    if ($action === 'validate') { $controller->validate($id); exit; }
    if ($action === 'reject')   { $controller->reject($id); exit; }
}

/*EVENTS*/
if ($path === '/events' && $method === 'GET') {
    (new EventController(new EventRepository($pdo)))->list();
    exit;
}

if ($path === '/events' && $method === 'POST') {
    AuthMiddleware::requireRole(['ORGANIZER']);
    (new EventController(new EventRepository($pdo)))->create();
    exit;
}

/*REGISTRATIONS*/
if ($method === 'POST' && preg_match('#^/events/(\d+)/register$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER']);
    $eventId = (int)$m[1];

    (new EventRegistrationController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    ))->register($eventId);
    exit;
}

if ($method === 'POST' && preg_match('#^/events/(\d+)/unregister$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER']);
    $eventId = (int)$m[1];

    (new EventRegistrationController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    ))->unregister($eventId);
    exit;
}

if ($method === 'GET' && preg_match('#^/events/(\d+)/registrations$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);
    $eventId = (int)$m[1];

    (new EventRegistrationController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    ))->list($eventId);
    exit;
}

if ($method === 'POST' && preg_match('#^/events/(\d+)/registrations/(\d+)/refuse$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);
    $eventId = (int)$m[1];
    $userId  = (int)$m[2];

    (new EventRegistrationController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    ))->refuse($eventId, $userId);
    exit;
}

/*LIFECYCLE*/
if ($method === 'POST' && preg_match('#^/events/(\d+)/start$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER']);
    $eventId = (int)$m[1];

    (new EventLifecycleController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    ))->start($eventId);
    exit;
}

if ($method === 'GET' && preg_match('#^/events/(\d+)/join$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER']);
    $eventId = (int)$m[1];

    (new EventLifecycleController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    ))->joinStatus($eventId);
    exit;
}

/*RESULTS (event)*/
if ($method === 'POST' && preg_match('#^/events/(\d+)/finish$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER']);
    $eventId = (int)$m[1];

    (new EventResultController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo),
        new ResultRepository($pdo)
    ))->finish($eventId);
    exit;
}

if ($method === 'GET' && preg_match('#^/events/(\d+)/standings$#', $path, $m)) {
    $eventId = (int)$m[1];

    (new EventResultController(
        new EventRepository($pdo),
        new RegistrationRepository($pdo),
        new ResultRepository($pdo)
    ))->standings($eventId);
    exit;
}

/*RESULTS (global)*/
if ($path === '/leaderboard' && $method === 'GET') {
    (new ResultController(new ResultRepository($pdo)))->leaderboard();
    exit;
}

/*ME*/
if ($path === '/me/results' && $method === 'GET') {
    AuthMiddleware::requireLogin();

    $meId = (int)($_SESSION['user']['id'] ?? 0);
    (new ResultController(new ResultRepository($pdo)))->myResults($meId);
    exit;
}

if ($path === '/me/stats' && $method === 'GET') {
    AuthMiddleware::requireLogin();
    (new MeController(new ResultRepository($pdo)))->stats();
    exit;
}

/*USERS (public-ish) - sécurisé: ADMIN ou soi-même*/
if ($method === 'GET' && preg_match('#^/users/(\d+)/(stats|results)$#', $path, $m)) {
    AuthMiddleware::requireLogin();

    $uid = (int)$m[1];
    $action = $m[2];

    $me = (int)($_SESSION['user']['id'] ?? 0);
    $role = (string)($_SESSION['user']['role'] ?? '');

    if ($role !== 'ADMIN' && $me !== $uid) {
        http_response_code(403);
        echo json_encode(['error' => 'Accès interdit'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $controller = new UserController(new ResultRepository($pdo));

    if ($action === 'stats') {
        $controller->stats($uid);
        exit;
    }

    if ($action === 'results') {
        $controller->results($uid);
        exit;
    }
}

/*CHAT*/
if ($method === 'GET' && preg_match('#^/events/(\d+)/chat$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER','ORGANIZER','ADMIN']);
    $eventId = (int)$m[1];

    (new ChatController(
        new ChatRepository(MongoClientFactory::db()),
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    ))->list($eventId);
    exit;
}

if ($method === 'POST' && preg_match('#^/events/(\d+)/chat$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER','ORGANIZER','ADMIN']);
    $eventId = (int)$m[1];

    (new ChatController(
        new ChatRepository(MongoClientFactory::db()),
        new EventRepository($pdo),
        new RegistrationRepository($pdo)
    ))->post($eventId);
    exit;
}

/*HEALTH*/
if ($path === '/health' && $method === 'GET') {
    (new HealthController($pdo, MongoClientFactory::db()))->check();
    exit;
}

/*FALLBACK*/
echo json_encode(['message' => 'API Esportify'], JSON_UNESCAPED_UNICODE);