<?php

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;

        $config = require __DIR__ . '/../../../.env.php';
        $dsn  = $config['db']['dsn'];
        $user = $config['db']['user'];
        $pass = $config['db']['pass'];

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Ne jamais exposer l’erreur en prod
            throw new \RuntimeException('DB connection failed');
        }

        return self::$pdo;
    }
}
