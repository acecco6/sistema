<?php

namespace App\Domain\Memberships\Repositories;

use App\Domain\Memberships\Entities\Membership;

interface MembershipRepository
{
    public function findById(int $id): ?Membership;
    public function findForUserAndClub(int $userId, int $clubId, ?int $branchId = null): ?Membership;
    public function create(Membership $membership): Membership;
}
