<?php

use App\Application\Notifications\Services\NotificationChannelResolver;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return $user && (int) $user->id === (int) $id;
});

// Canal por sucursal: private-club.{clubId}.branch.{branchId}
Broadcast::channel('private-club.{clubId}.branch.{branchId}', function ($user, int $clubId, int $branchId) {
    dump(['USER_BRANCH_CALLBACK' => $user?->id, 'CLUB_ID' => $clubId, 'BRANCH_ID' => $branchId]);
    if (!$user) {
        return false;
    }
    /** @var NotificationChannelResolver $resolver */
    $resolver = app(NotificationChannelResolver::class);

    return $resolver->userCanAccessBranch((int) $user->id, $clubId, $branchId);
});

Broadcast::channel('club.{clubId}.branch.{branchId}', function ($user, int $clubId, int $branchId) {
    dump(['USER_BRANCH_CALLBACK2' => $user?->id, 'CLUB_ID' => $clubId, 'BRANCH_ID' => $branchId]);
    if (!$user) {
        return false;
    }
    /** @var NotificationChannelResolver $resolver */
    $resolver = app(NotificationChannelResolver::class);

    return $resolver->userCanAccessBranch((int) $user->id, $clubId, $branchId);
});

// Canal global de club: private-club.{clubId}
Broadcast::channel('private-club.{clubId}', function ($user, int $clubId) {
    dump(['USER_CLUB_CALLBACK' => $user?->id, 'CLUB_ID' => $clubId]);
    if (!$user) {
        return false;
    }
    /** @var NotificationChannelResolver $resolver */
    $resolver = app(NotificationChannelResolver::class);

    return $resolver->userCanAccessClub((int) $user->id, $clubId);
});

Broadcast::channel('club.{clubId}', function ($user, int $clubId) {
    dump(['USER_CLUB_CALLBACK2' => $user?->id, 'CLUB_ID' => $clubId]);
    if (!$user) {
        return false;
    }
    /** @var NotificationChannelResolver $resolver */
    $resolver = app(NotificationChannelResolver::class);

    return $resolver->userCanAccessClub((int) $user->id, $clubId);
});
