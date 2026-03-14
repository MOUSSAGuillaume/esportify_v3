<?php

declare(strict_types=1);

namespace App\Service;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

final class MailerService
{
    public function sendWelcomeMail(string $toEmail, string $toName): void
    {
        $host = $_ENV['SMTP_AUTH_HOST'] ?? getenv('SMTP_AUTH_HOST') ?: '';
        $port = (int) ($_ENV['SMTP_AUTH_PORT'] ?? getenv('SMTP_AUTH_PORT') ?: 587);
        $username = $_ENV['SMTP_AUTH_USER'] ?? getenv('SMTP_AUTH_USER') ?: '';
        $password = $_ENV['SMTP_AUTH_PASS'] ?? getenv('SMTP_AUTH_PASS') ?: '';
        $fromEmail = $_ENV['SMTP_AUTH_FROM'] ?? getenv('SMTP_AUTH_FROM') ?: $username;
        $fromName = $_ENV['SMTP_AUTH_FROM_NAME'] ?? getenv('SMTP_AUTH_FROM_NAME') ?: 'Esportify';

        if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
            throw new RuntimeException('Configuration SMTP auth incomplète');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->SMTPDebug = 2;
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = 'Bienvenue sur Esportify';
            $mail->Body = '
                <h1>Bienvenue sur Esportify</h1>
                <p>Bonjour <strong>' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
                <p>Votre compte a bien été créé.</p>
                <p>Vous pouvez maintenant vous connecter et participer aux événements.</p>
            ';
            $mail->AltBody =
                "Bienvenue sur Esportify\n\n" .
                "Bonjour {$toName},\n" .
                "Votre compte a bien été créé.\n" .
                "Vous pouvez maintenant vous connecter et participer aux événements.";

            $mail->send();
        } catch (Exception $e) {
            throw new RuntimeException("Impossible d'envoyer le mail : " . $e->getMessage());
        }
    }

    public function sendVerificationMail(string $toEmail, string $toName, string $token): void
    {
        $host = $_ENV['SMTP_AUTH_HOST'] ?? getenv('SMTP_AUTH_HOST') ?: '';
        $port = (int) ($_ENV['SMTP_AUTH_PORT'] ?? getenv('SMTP_AUTH_PORT') ?: 587);
        $username = $_ENV['SMTP_AUTH_USER'] ?? getenv('SMTP_AUTH_USER') ?: '';
        $password = $_ENV['SMTP_AUTH_PASS'] ?? getenv('SMTP_AUTH_PASS') ?: '';
        $fromEmail = $_ENV['SMTP_AUTH_FROM'] ?? getenv('SMTP_AUTH_FROM') ?: $username;
        $fromName = $_ENV['SMTP_AUTH_FROM_NAME'] ?? getenv('SMTP_AUTH_FROM_NAME') ?: 'Esportify Auth';

        if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
            throw new RuntimeException('Configuration SMTP auth incomplète');
        }

        $verifyUrl = 'http://localhost:8080/verify-email?token=' . urlencode($token);

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->SMTPDebug = 2;
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = 'Activez votre compte Esportify';
            $mail->Body = '
            <h1>Bienvenue sur Esportify</h1>
            <p>Bonjour <strong>' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') . '</strong>,</p>
            <p>Merci pour votre inscription.</p>
            <p>Pour activer votre compte, cliquez sur le bouton ci-dessous :</p>
            <p>
                <a href="' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '" 
                   style="display:inline-block;padding:12px 20px;background:#6d4aff;color:#fff;text-decoration:none;border-radius:8px;">
                    Activer mon compte
                </a>
            </p>
            <p>Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :</p>
            <p>' . htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8') . '</p>
        ';
            $mail->AltBody =
                "Bienvenue sur Esportify\n\n" .
                "Bonjour {$toName},\n" .
                "Activez votre compte via ce lien : {$verifyUrl}";

            $mail->send();
        } catch (Exception $e) {
            throw new RuntimeException("Impossible d'envoyer le mail : " . $e->getMessage());
        }
    }

    public function sendContactMail(string $name, string $email, string $subject, string $message): void
    {
        $host = $_ENV['SMTP_CONTACT_HOST'] ?? getenv('SMTP_CONTACT_HOST') ?: '';
        $port = (int) ($_ENV['SMTP_CONTACT_PORT'] ?? getenv('SMTP_CONTACT_PORT') ?: 587);
        $username = $_ENV['SMTP_CONTACT_USER'] ?? getenv('SMTP_CONTACT_USER') ?: '';
        $password = $_ENV['SMTP_CONTACT_PASS'] ?? getenv('SMTP_CONTACT_PASS') ?: '';
        $fromEmail = $_ENV['SMTP_CONTACT_FROM'] ?? getenv('SMTP_CONTACT_FROM') ?: $username;
        $fromName = $_ENV['SMTP_CONTACT_FROM_NAME'] ?? getenv('SMTP_CONTACT_FROM_NAME') ?: 'Esportify Contact';
        $receiver = $_ENV['MAIL_RECEIVER'] ?? getenv('MAIL_RECEIVER') ?: '';

        if ($host === '' || $username === '' || $password === '' || $fromEmail === '' || $receiver === '') {
            throw new RuntimeException('Configuration SMTP contact incomplète');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($receiver, 'Esportify Admin');
            $mail->addReplyTo($email, $name);

            $mail->isHTML(true);
            $mail->Subject = '[Contact Esportify] ' . $subject;
            $mail->Body = '
            <h2>Nouveau message de contact</h2>
            <p><strong>Nom :</strong> ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Email :</strong> ' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</p>
            <p><strong>Sujet :</strong> ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</p>
            <hr>
            <p><strong>Message :</strong></p>
            <p>' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>
        ';
            $mail->AltBody =
                "Nouveau message de contact\n\n" .
                "Nom: {$name}\n" .
                "Email: {$email}\n" .
                "Sujet: {$subject}\n\n" .
                "Message:\n{$message}";

            $mail->send();
        } catch (Exception $e) {
            throw new RuntimeException("Impossible d'envoyer le mail de contact : " . $e->getMessage());
        }
    }
}
