<?php
declare(strict_types=1);

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

// Toutes les routes sont ici :
require __DIR__ . '/../config/routes.php';

// fallback
echo json_encode(['message' => 'API Esportify'], JSON_UNESCAPED_UNICODE);