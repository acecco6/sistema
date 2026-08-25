<?php

namespace App\Domain\Memberships\Repositories;

use App\Domain\Memberships\Entities\Membership;

interface MembershipRepository
{
    public function findById(int $id): ?Membership;
    public function findForUserAndClub(int $userId, int $clubId, ?int $branchId = null): ?Membership;
    public function create(Membership $membership): Membership;
    public function changeStatus(int $membershipId): void;
    public function changeRole(int $membershipId, int $roleId): void;
    public function update(Membership $membership): ?Membership;
    public function hasConflictingMembership(int $userId, int $clubId, ?int $branchId, ?int $excludeMembershipId = null): bool;
    public function findActiveForScope(int $userId, int $clubId, ?int $branchId = null): ?Membership;
    public function findActiveForClub(int $userId, int $clubId): array;
    public function hasActiveMemberships(int $userId): bool;
}
