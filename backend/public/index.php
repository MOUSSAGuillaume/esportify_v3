<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
  'ok' => true,
  'message' => 'Esportify API running (local)',
  'time' => date('c')
], JSON_UNESCAPED_UNICODE);