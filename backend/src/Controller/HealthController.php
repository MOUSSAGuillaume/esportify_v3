<?php
declare(strict_types=1);

namespace App\Controller;

use PDO;
use MongoDB\Database;

final class HealthController
{
    public function __construct(
        private PDO $pdo,
        private Database $mongoDb
    ) {}

    public function check(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $mysqlOk = false;
        $mongoOk = false;

        try {
            $this->pdo->query('SELECT 1')->fetchColumn();
            $mysqlOk = true;
        } catch (\Throwable $e) {
            $mysqlOk = false;
        }

        try {
            // ping rapide
            $this->mongoDb->command(['ping' => 1])->toArray();
            $mongoOk = true;
        } catch (\Throwable $e) {
            $mongoOk = false;
        }

        $ok = $mysqlOk && $mongoOk;

        http_response_code($ok ? 200 : 500);
        echo json_encode([
            'ok' => $ok,
            'mysql' => $mysqlOk,
            'mongo' => $mongoOk,
        ], JSON_UNESCAPED_UNICODE);
    }
}