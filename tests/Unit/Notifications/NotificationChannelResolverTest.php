<?php

use App\Application\Notifications\Services\NotificationChannelResolver;
use App\Domain\Memberships\Entities\Membership;
use App\Domain\Memberships\Repositories\MembershipRepository;

describe('NotificationChannelResolver', function () {
    test('devuelve canal global de club cuando la membresia es global y activa', function () {
        $repository = Mockery::mock(MembershipRepository::class);
        $repository->shouldReceive('findActiveForClub')
            ->with(1, 10)
            ->andReturn([
                new Membership(id: 1, userId: 1, clubId: 10, roleId: 2, branchId: null, active: true),
            ]);

        $resolver = new NotificationChannelResolver($repository);
        $channels = $resolver->resolveForClub(1, 10);

        expect($channels)->toBe(['club.10']);
        expect($resolver->userCanAccessClub(1, 10))->toBeTrue();
        expect($resolver->userCanAccessBranch(1, 10, 5))->toBeTrue();
    });

    test('devuelve canales por sucursal cuando el usuario no tiene membresia global', function () {
        $repository = Mockery::mock(MembershipRepository::class);
        $repository->shouldReceive('findActiveForClub')
            ->with(1, 20)
            ->andReturn([
                new Membership(id: 2, userId: 1, clubId: 20, roleId: 3, branchId: 6, active: true),
                new Membership(id: 3, userId: 1, clubId: 20, roleId: 4, branchId: 8, active: true),
            ]);

        $resolver = new NotificationChannelResolver($repository);
        $channels = $resolver->resolveForClub(1, 20);

        expect($channels)->toBe(['club.20.branch.6', 'club.20.branch.8']);
        expect($resolver->userCanAccessClub(1, 20))->toBeFalse();
        expect($resolver->userCanAccessBranch(1, 20, 6))->toBeTrue();
        expect($resolver->userCanAccessBranch(1, 20, 8))->toBeTrue();
        expect($resolver->userCanAccessBranch(1, 20, 99))->toBeFalse();
    });

    test('si existe membresia global omite los canales especificos de sucursal para evitar duplicados', function () {
        $repository = Mockery::mock(MembershipRepository::class);
        $repository->shouldReceive('findActiveForClub')
            ->with(1, 30)
            ->andReturn([
                new Membership(id: 4, userId: 1, clubId: 30, roleId: 2, branchId: null, active: true),
                new Membership(id: 5, userId: 1, clubId: 30, roleId: 3, branchId: 6, active: true),
            ]);

        $resolver = new NotificationChannelResolver($repository);
        $channels = $resolver->resolveForClub(1, 30);

        expect($channels)->toBe(['club.30']);
    });

    test('devuelve vacio cuando el usuario no tiene membresias activas', function () {
        $repository = Mockery::mock(MembershipRepository::class);
        $repository->shouldReceive('findActiveForClub')
            ->with(1, 40)
            ->andReturn([]);

        $resolver = new NotificationChannelResolver($repository);
        $channels = $resolver->resolveForClub(1, 40);

        expect($channels)->toBe([]);
        expect($resolver->userCanAccessClub(1, 40))->toBeFalse();
        expect($resolver->userCanAccessBranch(1, 40, 1))->toBeFalse();
    });
});
