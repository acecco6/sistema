<?php

namespace App\Application\Memberships\ChangeBranche;

final class ChangeMembershipBranchCommand
{
    public function __construct(
        public readonly int $membershipId,
        public readonly ?int $branchId,
    ) {}
}
