<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\Csrf;

final class CsrfController
{
    public function token(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['token' => Csrf::token()]);
    }
}
