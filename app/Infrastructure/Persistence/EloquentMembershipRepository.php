<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Memberships\Entities\Membership;
use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Models\Membership as EloquentMembership;

final class EloquentMembershipRepository implements MembershipRepository
{
    public function findById(int $id): ?Membership
    {
        $membership = EloquentMembership::find($id);

        return $membership
            ? $this->toDomain($membership)
            : null;
    }

    public function create(Membership $membership): Membership
    {
        $eloquentMembership = EloquentMembership::create([
            'user_id' => $membership->getUserId(),
            'club_id' => $membership->getClubId(),
            'role_id' => $membership->getRoleId(),
            'branch_id' => $membership->getBranchId(),
            'active' => $membership->isActive(),
        ]);

        return $this->toDomain($eloquentMembership);
    }

    public function update(Membership $membership): ?Membership
    {
        $eloquentMembership = EloquentMembership::find($membership->getId());

        if (!$eloquentMembership) {
            return null;
        }

        $eloquentMembership->update([
            'user_id' => $membership->getUserId(),
            'club_id' => $membership->getClubId(),
            'role_id' => $membership->getRoleId(),
            'branch_id' => $membership->getBranchId(),
            'active' => $membership->isActive(),
        ]);

        return $this->toDomain($eloquentMembership);
    }

    public function desactivate(int $id): ?Membership
    {
        $membership = EloquentMembership::find($id);

        if (!$membership) {
            return null;
        }

        $membership->update([
            'active' => false,
        ]);

        return $this->toDomain($membership);
    }

    public function findForUserAndClub(int $userId, int $clubId, ?int $branchId = null): ?Membership
    {
        $query = EloquentMembership::query()
            ->where('user_id', $userId)
            ->where('club_id', $clubId);

        if ($branchId === null) {
            $query->whereNull('branch_id');
        } else {
            $query->where('branch_id', $branchId);
        }

        $membership = $query->first();

        return $membership
            ? $this->toDomain($membership)
            : null;
    }

    private function toDomain(EloquentMembership $membership): Membership
    {
        return new Membership(
            id: $membership->id,
            userId: $membership->user_id,
            clubId: $membership->club_id,
            roleId: $membership->role_id,
            branchId: $membership->branch_id,
            active: $membership->active,
        );
    }
}
