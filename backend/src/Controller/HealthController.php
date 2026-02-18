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

    // GET /health
    public function check(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $out = [
            'ok' => true,
            'mysql' => ['ok' => true],
            'mongo' => ['ok' => true],
            'time' => gmdate('c'),
        ];

        // MySQL
        try {
            $this->pdo->query('SELECT 1')->fetchColumn();
        } catch (\Throwable $e) {
            $out['ok'] = false;
            $out['mysql'] = ['ok' => false, 'error' => 'mysql_down'];
        }

        // Mongo
        try {
            // ping admin
            $this->mongoDb->command(['ping' => 1])->toArray();
        } catch (\Throwable $e) {
            $out['ok'] = false;
            $out['mongo'] = ['ok' => false, 'error' => 'mongo_down'];
        }

        http_response_code($out['ok'] ? 200 : 503);
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    }
}