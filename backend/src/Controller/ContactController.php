<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContactMessageRepository;
use App\Service\MailerService;
use Throwable;

final class ContactController
{
    public function __construct(
        private ContactMessageRepository $messages,
        private MailerService $mailer
    ) {}

    public function send(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $subject = trim($data['subject'] ?? '');
        $message = trim($data['message'] ?? '');
        $company = trim($data['company'] ?? '');

        try {

            // honeypot anti-spam
            if ($company !== '') {
                http_response_code(400);
                echo json_encode(['error' => 'Requête invalide']);
                return;
            }

            if (strlen($name) < 2) {
                throw new \InvalidArgumentException('Nom invalide');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Email invalide');
            }

            if (strlen($subject) < 3) {
                throw new \InvalidArgumentException('Sujet invalide');
            }

            if (strlen($message) < 10) {
                throw new \InvalidArgumentException('Message trop court');
            }

            // sauvegarde en base
            $this->messages->create($name, $email, $subject, $message);

            // envoi mail
            try {
                $this->mailer->sendContactMail($name, $email, $subject, $message);
            } catch (Throwable $e) {
                error_log('Contact mail error: ' . $e->getMessage());
            }

            http_response_code(201);
            echo json_encode([
                'message' => 'Message envoyé avec succès'
            ]);
        } catch (Throwable $e) {

            http_response_code(400);

            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }
}
