<?php

namespace App\Application\Memberships\ChangeStatus;

use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Domain\Memberships\Exceptions\MembershipNotFoundException;

final class ChangeStatusMembershipHandler
{
    public function __construct(
        private MembershipRepository $membershipRepository
    ) {}

    public function handle(ChangeStatusMembershipCommand $command): void
    {
        $membership = $this->membershipRepository->findById($command->id);

        if ($membership === null) {
            throw new MembershipNotFoundException();
        }

        $membership->changeStatus();

        $this->membershipRepository->update($membership);
    }
}
