<?php

namespace App\Application\Memberships\Show;

final readonly class ShowMembershipCommand
{
    public function __construct(
        public int $membershipId,
        public int $authenticatedUserId,
    ) {}
}
