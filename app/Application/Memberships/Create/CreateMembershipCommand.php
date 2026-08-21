<?php

namespace App\Application\Memberships\Create;

final readonly class CreateMembershipCommand
{
    public function __construct(
        public int $userId,
        public int $clubId,
        public int $roleId,
        public ?int $branchId
    ) {}
}
