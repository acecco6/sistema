<?php

namespace App\Application\Memberships\ChangeStatus;

final class ChangeStatusMembershipCommand
{
    public function __construct(
        public readonly int $id,
    ) {}
}
