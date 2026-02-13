<?php
require __DIR__ . '/vendor/autoload.php';

$uri = getenv('MONGO_URI');
$db  = getenv('MONGO_DB') ?: 'esportify_chat';

var_dump($uri, $db);

$client = new MongoDB\Client($uri);
echo "CONNECTED\n";