<?php

namespace App\Domain\Memberships\Entities;

use App\Domain\Memberships\Exceptions\InvalidMembershipBranchChangeException;
use App\Domain\Memberships\Exceptions\MembershipAlreadyInactiveException;

final class Membership
{
    public function __construct(
        private ?int $id,
        private int $userId,
        private int $clubId,
        private int $roleId,
        private ?int $branchId,
        private bool $active = true,
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getClubId(): int
    {
        return $this->clubId;
    }

    public function getRoleId(): int
    {
        return $this->roleId;
    }

    public function getBranchId(): ?int
    {
        return $this->branchId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function changeRole(int $roleId): void
    {
        $this->roleId = $roleId;
    }

    public function changeStatus(): void
    {
        $this->active = ! $this->active;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function changeBranch(?int $branchId): void
    {
        $currentBranchId = $this->branchId;

        if ($currentBranchId !== null && $branchId !== null && $currentBranchId !== $branchId) {
            throw new InvalidMembershipBranchChangeException();
        }

        $this->branchId = $branchId;
    }

    public function deactivate(): void
    {
        if (! $this->active) {
            throw new MembershipAlreadyInactiveException();
        }
        $this->active = false;
    }
}
