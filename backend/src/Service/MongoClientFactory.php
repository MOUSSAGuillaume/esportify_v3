<?php
declare(strict_types=1);

namespace App\Service;

use MongoDB\Client;
use MongoDB\Database;

final class MongoClientFactory
{
    public static function db(): Database
    {
        $uri = getenv('MONGO_URI') ?: 'mongodb://mongo:27017';
        $dbName = getenv('MONGO_DB') ?: 'esportify_chat';

        $client = new Client($uri);
        return $client->selectDatabase($dbName);
    }
}