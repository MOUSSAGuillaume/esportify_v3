<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use App\Repository\RegistrationRepository;
use App\Security\Csrf;
use Throwable;

final class EventController
{
    public function __construct(
        private EventRepository $events,
        private ?RegistrationRepository $regs = null
    ) {}

    public function create(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;
        if (!Csrf::validate($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $title = trim((string)($data['title'] ?? ''));
            $desc  = trim((string)($data['description'] ?? ''));
            $start = (string)($data['start_at'] ?? '');
            $end   = (string)($data['end_at'] ?? '');
            $max   = (int)($data['max_players'] ?? 0);

            if ($title === '' || $desc === '' || $start === '' || $end === '' || $max <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Champs invalides'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $organizerId = (int)($_SESSION['user']['id'] ?? 0);
            $id = $this->events->create([
                'title' => $title,
                'description' => $desc,
                'start_at' => $start,
                'end_at' => $end,
                'max_players' => $max,
            ], $organizerId);

            http_response_code(201);
            echo json_encode(['message' => 'Événement créé (en attente)', 'id' => $id], JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function list(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $q     = isset($_GET['q']) ? (string)$_GET['q'] : null;
        $from  = isset($_GET['from']) ? (string)$_GET['from'] : null;
        $to    = isset($_GET['to']) ? (string)$_GET['to'] : null;
        $sort  = isset($_GET['sort']) ? (string)$_GET['sort'] : null;
        $order = isset($_GET['order']) ? (string)$_GET['order'] : null;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;

        $events = $this->events->listValidatedFiltered($q, $from, $to, $sort, $order, $limit);
        echo json_encode(['events' => $events], JSON_UNESCAPED_UNICODE);
    }

    public function show(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $event = $this->events->findDetailById($id);
        if (!$event) {
            http_response_code(404);
            echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $meId = (int)($_SESSION['user']['id'] ?? 0);
        if ($meId > 0 && $this->regs) {
            $event['is_registered'] = $this->regs->isActive($id, $meId);
        } else {
            $event['is_registered'] = false;
        }

        // utile pour le front
        $event['registered_count'] = $this->regs ? $this->regs->countActive($id) : 0;

        echo json_encode(['event' => $event], JSON_UNESCAPED_UNICODE);
    }

    public function update(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;

        if (!Csrf::validate($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $title = trim((string)($data['title'] ?? ''));
            $desc  = trim((string)($data['description'] ?? ''));
            $start = (string)($data['start_at'] ?? '');
            $end   = (string)($data['end_at'] ?? '');
            $max   = (int)($data['max_players'] ?? 0);

            if ($title === '' || $desc === '' || $start === '' || $end === '' || $max <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Champs invalides'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $event = $this->events->findById($id);
            if (!$event) {
                http_response_code(404);
                echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $this->events->updateById($id, [
                'title'       => $title,
                'description' => $desc,
                'start_at'    => $start,
                'end_at'      => $end,
                'max_players' => $max,
            ]);

            echo json_encode(['message' => 'Événement mis à jour'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function delete(int $id): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;

        if (!Csrf::validate($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        try {
            $event = $this->events->findById($id);
            if (!$event) {
                http_response_code(404);
                echo json_encode(['error' => 'Événement introuvable'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $ok = $this->events->deleteById($id);
            if (!$ok) {
                http_response_code(500);
                echo json_encode(['error' => 'Suppression impossible'], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode(['message' => 'Événement supprimé'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur'], JSON_UNESCAPED_UNICODE);
        }
    }
}
