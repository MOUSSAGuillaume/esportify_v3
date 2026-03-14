<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use InvalidArgumentException;

final class AuthService
{
    public function __construct(
        private UserRepository $users,
        private ?MailerService $mailer = null
    ) {}

    public function register(string $email, string $password, string $pseudo): bool
    {
        $email = trim($email);
        $pseudo = trim($pseudo);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Email invalide");
        }

        if (mb_strlen($pseudo) < 3 || mb_strlen($pseudo) > 30) {
            throw new InvalidArgumentException("Pseudo invalide (3 à 30 caractères)");
        }

        if (
            mb_strlen($password) < 8 ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/\d/', $password) ||
            !preg_match('/[\W_]/', $password)
        ) {
            throw new InvalidArgumentException(
                "Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial"
            );
        }

        if ($this->users->findByEmail($email)) {
            throw new InvalidArgumentException("Email déjà utilisé");
        }

        if ($this->users->findByPseudo($pseudo)) {
            throw new InvalidArgumentException("Pseudo déjà utilisé");
        }

        $hash = PasswordHasher::hash($password);
        $token = bin2hex(random_bytes(32));

        $this->users->createPending($email, $hash, $pseudo, $token);

        $mailSent = false;

        if ($this->mailer !== null) {
            try {
                $this->mailer->sendVerificationMail($email, $pseudo, $token);
                $mailSent = true;
            } catch (\Throwable $e) {
                error_log('Signup mail error: ' . $e->getMessage());
            }
        }

        return $mailSent;
    }

    public function verifyEmail(string $token): void
    {
        $token = trim($token);

        if ($token === '') {
            throw new InvalidArgumentException("Token invalide");
        }

        $user = $this->users->findByVerificationToken($token);

        if (!$user) {
            throw new InvalidArgumentException("Lien de validation invalide ou expiré");
        }

        if ((int)($user['is_verified'] ?? 0) === 1) {
            return;
        }

        $this->users->markVerified((int)$user['id']);
    }

    public function login(string $email, string $password): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Email invalide");
        }

        $user = $this->users->findByEmail($email);

        if (!$user || !PasswordHasher::verify($password, $user['password_hash'])) {
            throw new InvalidArgumentException("Identifiants invalides");
        }

        if ((int)($user['is_verified'] ?? 0) !== 1) {
            throw new InvalidArgumentException("Veuillez vérifier votre adresse email avant de vous connecter");
        }

        return [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'pseudo' => $user['pseudo'],
            'role' => $user['role'],
        ];
    }
}
