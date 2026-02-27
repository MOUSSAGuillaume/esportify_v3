<?php
declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\EventRepository;
use App\Repository\ContactMessageRepository;

final class AdminStatsController
{
    public function __construct(
        private UserRepository $users,
        private EventRepository $events,
        private ContactMessageRepository $messages
    ) {}

    public function stats(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $data = [
            'users' => [
                'total' => $this->users->countAll(),
                'byRole' => [
                    'PLAYER' => $this->users->countByRole('PLAYER'),
                    'ORGANIZER' => $this->users->countByRole('ORGANIZER'),
                    'ADMIN' => $this->users->countByRole('ADMIN'),
                ],
                'suspended' => $this->users->countSuspended(),
            ],
            'events' => [
                'total' => $this->events->countAll(),
                'byStatus' => [
                    'PENDING' => $this->events->countByStatus('PENDING'),
                    'VALIDATED' => $this->events->countByStatus('VALIDATED'),
                    'REJECTED' => $this->events->countByStatus('REJECTED'),
                    'SUSPENDED' => $this->events->countByStatus('SUSPENDED'),
                ],
            ],
            'messages' => [
                'unread' => $this->messages->countUnread(),
            ],
        ];

        echo json_encode(['stats' => $data], JSON_UNESCAPED_UNICODE);
    }
}