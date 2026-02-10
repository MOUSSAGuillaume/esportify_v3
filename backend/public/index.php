<?php
declare(strict_types=1);
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '0'); // local http

session_start();

use App\Controller\AuthController;
use App\Repository\UserRepository;
use App\Service\AuthService;

require __DIR__ . '/../vendor/autoload.php';

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

echo json_encode(['message' => 'API Esportify']);