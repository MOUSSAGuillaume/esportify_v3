<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use App\Security\Csrf;
use Throwable;

final class EventController
{
    public function __construct(private EventRepository $events) {}

    public function create(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        // CSRF
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        $csrf = is_string($csrf) ? trim($csrf, " \t\n\r\0\x0B\"'") : null;
        if (!Csrf::validate($csrf)) {
            http_response_code(403);
            echo json_encode(['error' => 'CSRF invalide'], JSON_UNESCAPED_UNICODE);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            // validation minimale
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
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erreur serveur'], JSON_UNESCAPED_UNICODE);
        }
    }

    public function list(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $sort = $_GET['sort'] ?? null;   // date|players|organizer
        $order = $_GET['order'] ?? null; // asc|desc

        $events = $this->events->listValidated($sort, $order);
        echo json_encode(['events' => $events], JSON_UNESCAPED_UNICODE);
    }
}