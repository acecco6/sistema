<?php

namespace App\Application\Notifications\Services;

use App\Domain\Memberships\Entities\Membership;
use App\Domain\Memberships\Repositories\MembershipRepository;

final class NotificationChannelResolver
{
    public function __construct(
        private readonly MembershipRepository $membershipRepository
    ) {}

    /**
     * Devuelve los nombres de canales autorizados para un usuario dentro de un club.
     * 
     * Regla principal:
     * - Si posee una membresía global activa (branchId === null), devuelve exclusivamente ["club.{$clubId}"].
     * - Si no posee membresía global pero tiene membresías activas por sucursal (branchId !== null),
     *   devuelve ["club.{$clubId}.branch.{$branchId}"] para cada sucursal activa sin duplicados.
     * - Si no tiene ninguna membresía activa en dicho club, devuelve [].
     *
     * @return array<string>
     */
    public function resolveForClub(int $userId, int $clubId): array
    {
        $memberships = $this->membershipRepository->findActiveForClub($userId, $clubId);

        if (empty($memberships)) {
            return [];
        }

        $hasGlobalMembership = collect($memberships)->contains(
            fn (Membership $membership) => $membership->getBranchId() === null
        );

        if ($hasGlobalMembership) {
            return ["club.{$clubId}"];
        }

        return collect($memberships)
            ->filter(fn (Membership $membership) => $membership->getBranchId() !== null)
            ->map(fn (Membership $membership) => "club.{$clubId}.branch.{$membership->getBranchId()}")
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Verifica si un usuario tiene acceso al canal global de un club.
     */
    public function userCanAccessClub(int $userId, int $clubId): bool
    {
        $memberships = $this->membershipRepository->findActiveForClub($userId, $clubId);

        return collect($memberships)->contains(
            fn (Membership $membership) => $membership->getBranchId() === null
        );
    }

    /**
     * Verifica si un usuario tiene acceso al canal de una sucursal específica dentro de un club.
     */
    public function userCanAccessBranch(int $userId, int $clubId, int $branchId): bool
    {
        $memberships = $this->membershipRepository->findActiveForClub($userId, $clubId);

        return collect($memberships)->contains(
            fn (Membership $membership) => $membership->getBranchId() === null || $membership->getBranchId() === $branchId
        );
    }
}
