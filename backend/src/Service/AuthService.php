<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use InvalidArgumentException;

final class AuthService
{
    public function __construct(private UserRepository $users) {}

    public function register(string $email, string $password, string $pseudo): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Email invalide");
        }

        if (strlen($password) < 6) {
            throw new InvalidArgumentException("Mot de passe trop court");
        }

        if ($this->users->findByEmail($email)) {
            throw new InvalidArgumentException("Email déjà utilisé");
        }

        $hash = PasswordHasher::hash($password);
        $this->users->create($email, $hash, $pseudo);
    }
}