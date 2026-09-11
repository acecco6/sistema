<?php

namespace App\Application\Notifications;

use App\Application\Notifications\Services\NotificationChannelResolver;
use App\Shared\Exceptions\AuthorizationDeniedException;

final readonly class GetNotificationChannels
{
    public function __construct(
        private NotificationChannelResolver $resolver
    ) {}

    /**
     * @return array{club_id: int, channels: array<string>}
     */
    public function handle(int $userId, int $clubId): array
    {
        $channels = $this->resolver->resolveForClub($userId, $clubId);
        if ($channels === []) {
            throw new AuthorizationDeniedException();
        }

        return [
            'club_id' => $clubId,
            'channels' => $channels,
        ];
    }
}
