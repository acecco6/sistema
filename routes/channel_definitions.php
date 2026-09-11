<?php

use App\Application\Notifications\Services\NotificationChannelResolver;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user && (int) $user->id === (int) $id;
});

Broadcast::channel('club.{clubId}.branch.{branchId}', function ($user, int $clubId, int $branchId) {
    if (! $user) {
        return false;
    }

    /** @var NotificationChannelResolver $resolver */
    $resolver = app(NotificationChannelResolver::class);

    return $resolver->userCanAccessBranch((int) $user->id, $clubId, $branchId);
});

Broadcast::channel('club.{clubId}', function ($user, int $clubId) {
    if (! $user) {
        return false;
    }

    /** @var NotificationChannelResolver $resolver */
    $resolver = app(NotificationChannelResolver::class);

    return $resolver->userCanAccessClub((int) $user->id, $clubId);
});
