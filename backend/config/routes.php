<?php

declare(strict_types=1);

use App\Controller\AuthController;
use App\Controller\AdminEventController;
use App\Controller\AdminUserController;
use App\Controller\AdminMessageController;
use App\Controller\AdminStatsController;
use App\Controller\EventController;
use App\Controller\EventRegistrationController;
use App\Controller\EventLifecycleController;
use App\Controller\EventResultController;
use App\Controller\MeController;
use App\Controller\ResultController;
use App\Controller\ChatController;
use App\Controller\UserController;
use App\Controller\ProfileController;
use App\Controller\OrganizerController;
use App\Security\Csrf;
use App\Middleware\AuthMiddleware;
use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use App\Repository\ResultRepository;
use App\Repository\ChatRepository;
use App\Repository\ContactMessageRepository;
use App\Service\AuthService;
use App\Service\MongoClientFactory;

/*
Variables dispo depuis index.php:
- $pdo (PDO)
- $path (string)
- $method (string)
*/

$usersRepo = new UserRepository($pdo);
$eventsRepo = new EventRepository($pdo);
$regRepo = new RegistrationRepository($pdo);
$resultsRepo = new ResultRepository($pdo);
$contactRepo = new ContactMessageRepository($pdo);

/* ---------------- CSRF ---------------- */
if ($path === '/csrf' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['token' => Csrf::token()], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------------- PROFILE ---------------- */
if ($path === '/profile/me' && $method === 'GET') {
    AuthMiddleware::requireLogin();
    (new ProfileController($usersRepo, $regRepo, $eventsRepo))->me();
    exit;
}

if ($path === '/profile/me' && $method === 'PUT') {
    AuthMiddleware::requireLogin();
    (new ProfileController($usersRepo, $regRepo, $eventsRepo))->updateMe();
    exit;
}

/* ---------------- AUTH ---------------- */
if ($path === '/register' && $method === 'POST') {
    (new AuthController(new AuthService($usersRepo)))->register();
    exit;
}
if ($path === '/login' && $method === 'POST') {
    (new AuthController(new AuthService($usersRepo)))->login();
    exit;
}
if ($path === '/logout' && $method === 'POST') {
    (new AuthController(new AuthService($usersRepo)))->logout();
    exit;
}
if ($path === '/me' && $method === 'GET') {
    (new AuthController(new AuthService($usersRepo)))->me();
    exit;
}

/* ---------------- HEALTH ---------------- */
if ($path === '/health' && $method === 'GET') {
    (new \App\Controller\HealthController($pdo, MongoClientFactory::db()))->check();
    exit;
}

/* ---------------- ADMIN ---------------- */
if ($path === '/admin/ping' && $method === 'GET') {
    AuthMiddleware::requireRole(['ADMIN']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($path === '/admin/stats' && $method === 'GET') {
    AuthMiddleware::requireRole(['ADMIN']);
    (new AdminStatsController($usersRepo, $eventsRepo, $contactRepo))->stats();
    exit;
}

if ($path === '/admin/events' && $method === 'GET') {
    AuthMiddleware::requireRole(['ADMIN']);
    (new AdminEventController($eventsRepo))->list();
    exit;
}

if ($method === 'POST' && preg_match('#^/admin/events/(\d+)/(validate|reject|suspend)$#', $path, $m)) {
    AuthMiddleware::requireRole(['ADMIN']);
    $id = (int)$m[1];
    $action = $m[2];
    $ctrl = new AdminEventController($eventsRepo);

    if ($action === 'validate') {
        $ctrl->validate($id);
        exit;
    }
    if ($action === 'reject') {
        $ctrl->reject($id);
        exit;
    }
    if ($action === 'suspend') {
        $ctrl->suspend($id);
        exit;
    }
}

if ($path === '/admin/users' && $method === 'GET') {
    AuthMiddleware::requireRole(['ADMIN']);
    (new AdminUserController($usersRepo))->list();
    exit;
}

if ($method === 'POST' && preg_match('#^/admin/users/(\d+)/role$#', $path, $m)) {
    AuthMiddleware::requireRole(['ADMIN']);
    (new AdminUserController($usersRepo))->updateRole((int)$m[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/admin/users/(\d+)/(suspend|unsuspend)$#', $path, $m)) {
    AuthMiddleware::requireRole(['ADMIN']);
    $ctrl = new AdminUserController($usersRepo);
    $uid = (int)$m[1];
    $m[2] === 'suspend' ? $ctrl->suspend($uid) : $ctrl->unsuspend($uid);
    exit;
}

if ($path === '/admin/messages' && $method === 'GET') {
    AuthMiddleware::requireRole(['ADMIN']);
    (new AdminMessageController($contactRepo))->list();
    exit;
}

if ($method === 'POST' && preg_match('#^/admin/messages/(\d+)/read$#', $path, $m)) {
    AuthMiddleware::requireRole(['ADMIN']);
    (new AdminMessageController($contactRepo))->markRead((int)$m[1]);
    exit;
}

/* ---------------- EVENTS ---------------- */
if ($path === '/events' && $method === 'GET') {
    (new EventController($eventsRepo))->list();
    exit;
}

if ($method === 'GET' && preg_match('#^/events/(\d+)$#', $path, $m)) {
    (new EventController($eventsRepo))->show((int)$m[1]);
    exit;
}

if ($path === '/events' && $method === 'POST') {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);
    (new EventController($eventsRepo))->create();
    exit;
}

/* ---------------- REGISTRATIONS ---------------- */
if ($method === 'POST' && preg_match('#^/events/(\d+)/register$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER']);
    (new EventRegistrationController($eventsRepo, $regRepo))->register((int)$m[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/events/(\d+)/unregister$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER']);
    (new EventRegistrationController($eventsRepo, $regRepo))->unregister((int)$m[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/events/(\d+)/registrations/(\d+)/refuse$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);
    (new EventRegistrationController($eventsRepo, $regRepo))->refuse((int)$m[1], (int)$m[2]);
    exit;
}

if ($method === 'GET' && preg_match('#^/events/(\d+)/registrations$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);
    (new EventRegistrationController($eventsRepo, $regRepo))->list((int)$m[1]);
    exit;
}

/* ---------------- ORGANIZER DASHBOARD ---------------- */
if ($path === '/organizer/events' && $method === 'GET') {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);
    (new OrganizerController($eventsRepo, $regRepo))->listMyEvents();
    exit;
}

if ($method === 'GET' && preg_match('#^/organizer/events/(\d+)/registrations$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);
    (new OrganizerController($eventsRepo, $regRepo))->eventRegistrations((int)$m[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/organizer/events/(\d+)/registrations/(\d+)/refuse$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);
    (new OrganizerController($eventsRepo, $regRepo))->refusePlayer((int)$m[1], (int)$m[2]);
    exit;
}

if ($method === 'POST' && preg_match('#^/organizer/events/(\d+)/start$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER', 'ADMIN']);
    (new OrganizerController($eventsRepo, $regRepo))->startEvent((int)$m[1]);
    exit;
}

/* ---------------- LIFECYCLE ---------------- */
if ($method === 'POST' && preg_match('#^/events/(\d+)/start$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER']);
    (new EventLifecycleController($eventsRepo, $regRepo))->start((int)$m[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/events/(\d+)/join$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER']);
    (new EventLifecycleController($eventsRepo, $regRepo))->joinStatus((int)$m[1]);
    exit;
}

/* ---------------- RESULTS ---------------- */
if ($method === 'POST' && preg_match('#^/events/(\d+)/finish$#', $path, $m)) {
    AuthMiddleware::requireRole(['ORGANIZER']);
    (new EventResultController($eventsRepo, $regRepo, $resultsRepo))->finish((int)$m[1]);
    exit;
}

if ($method === 'GET' && preg_match('#^/events/(\d+)/standings$#', $path, $m)) {
    (new EventResultController($eventsRepo, $regRepo, $resultsRepo))->standings((int)$m[1]);
    exit;
}

if ($path === '/leaderboard' && $method === 'GET') {
    (new ResultController($resultsRepo))->leaderboard();
    exit;
}

if ($path === '/me/results' && $method === 'GET') {
    AuthMiddleware::requireLogin();
    $meId = (int)($_SESSION['user']['id'] ?? 0);
    (new ResultController($resultsRepo))->myResults($meId);
    exit;
}

if ($path === '/me/stats' && $method === 'GET') {
    AuthMiddleware::requireLogin();
    (new MeController($resultsRepo))->stats();
    exit;
}

if ($path === '/me/registrations' && $method === 'GET') {
    AuthMiddleware::requireRole(['PLAYER', 'ORGANIZER', 'ADMIN']);
    $meId = (int)($_SESSION['user']['id'] ?? 0);
    (new EventRegistrationController($eventsRepo, $regRepo))->myRegistrations($meId);
    exit;
}

/* ---------------- USERS ---------------- */
if ($method === 'GET' && preg_match('#^/users/(\d+)/(stats|results)$#', $path, $m)) {
    AuthMiddleware::requireLogin();

    $uid = (int)$m[1];
    $action = $m[2];

    $me = (int)($_SESSION['user']['id'] ?? 0);
    $role = strtoupper((string)($_SESSION['user']['role'] ?? ''));

    if ($role !== 'ADMIN' && $me !== $uid) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Accès interdit'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $controller = new UserController($resultsRepo, $usersRepo);
    $action === 'stats' ? $controller->stats($uid) : $controller->results($uid);
    exit;
}

/* ---------------- CHAT ---------------- */

if ($method === 'GET' && preg_match('#^/events/(\d+)/chat$#', $path, $m)) {
    AuthMiddleware::requireLogin();
    (new ChatController(
        new ChatRepository(MongoClientFactory::db()),
        $eventsRepo,
        $regRepo
    ))->list((int)$m[1]);
    exit;
}

if ($method === 'POST' && preg_match('#^/events/(\d+)/chat$#', $path, $m)) {
    AuthMiddleware::requireRole(['PLAYER', 'ORGANIZER', 'ADMIN']);
    (new ChatController(
        new ChatRepository(MongoClientFactory::db()),
        $eventsRepo,
        $regRepo
    ))->post((int)$m[1]);
    exit;
}
