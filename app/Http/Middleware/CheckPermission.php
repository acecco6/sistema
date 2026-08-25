<?php

namespace App\Http\Middleware;

use App\Application\Authorization\AuthorizationService;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Memberships\Exceptions\MembershipNotFoundException;
use App\Domain\Memberships\Repositories\MembershipRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

final class CheckPermission
{
    public function __construct(
        private AuthorizationService $authorization,
        private BranchRepository $branches,
        private MembershipRepository $memberships,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $permission = $request->route()?->getName();

        if ($permission === null) {
            throw new RuntimeException(
                'La ruta no tiene un permiso asociado.'
            );
        }

        [$resource] = explode('.', $permission);
        $scope = match ($resource) {
            'club' => $this->resolveClubScope($request),
            'branch' => $this->resolveBranchScope($request),
            'membership' => $this->resolveMembershipScope($request),

            default => function () use ($request, $resource) {
                Log::error("Permiso no encontrado.", [
                    'user_id' => $request->user()->id,
                    'user_email' => $request->user()->email,
                    'request' => [
                        'method' => $request->method(),
                        'uri' => $request->fullUrl(),
                        'body' => $request->all(),
                        'headers' => $request->headers->all(),
                    ],
                    'resource' => $resource,
                    'route' => $request->route(),
                ]);
                throw new RuntimeException(
                    "Error de sistema: Permiso no encontrado."
                );
            }
        };
        // dd($scope, $permission, $user->id);

        $this->authorization->authorize(
            userId: $user->id,
            clubId: $scope['clubId'],
            branchId: $scope['branchId'],
            permission: $permission,
        );

        return $next($request);
    }

    private function resolveClubScope(Request $request): array
    {
        $clubId =
            $request->route('club_id')
            ?? $request->route('id')
            ?? $request->input('club_id');

        if ($clubId === null) {
            Log::error("No se pudo determinar el club.", [
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'request' => [
                    'method' => $request->method(),
                    'uri' => $request->fullUrl(),
                    'body' => $request->all(),
                    'headers' => $request->headers->all(),
                ],
            ]);
            throw new RuntimeException(
                'No se pudo determinar el club.'
            );
        }

        return [
            'clubId' => (int) $clubId,
            'branchId' => null,
        ];
    }

    private function resolveBranchScope(Request $request): array
    {
        /*
         * Caso:
         *
         * POST /clubs/{club_id}/branches
         *
         * La branch todavía no existe.
         * Autorizamos a nivel del club.
         */
        if ($request->route('club_id') !== null) {
            return [
                'clubId' => (int) $request->route('club_id'),
                'branchId' => null,
            ];
        }

        /*
         * Casos:
         *
         * GET    /branches/{id}
         * PUT    /branches/{id}
         * DELETE /branches/{id}
         */
        $branchId = $request->route('id');

        if ($branchId === null) {
            Log::error("No se pudo determinar la sucursal.", [
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'request' => [
                    'method' => $request->method(),
                    'uri' => $request->fullUrl(),
                    'body' => $request->all(),
                    'headers' => $request->headers->all(),
                ],
            ]);
            throw new RuntimeException(
                'No se pudo determinar la sucursal.'
            );
        }

        $branch = $this->branches->findById(
            (int) $branchId
        );

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        return [
            'clubId' => $branch->getClubId(),
            'branchId' => $branch->getId(),
        ];
    }

    private function resolveMembershipScope(Request $request): array
    {
        /*
         * POST /memberships
         */
        if ($request->route('id') === null) {
            $clubId = $request->input('club_id');

            if ($clubId === null) {
                throw new RuntimeException(
                    'No se pudo determinar el club.'
                );
            }

            return [
                'clubId' => (int) $clubId,
                'branchId' => $request->input('branch_id')
                    ? (int) $request->input('branch_id')
                    : null,
            ];
        }

        /*
         * PATCH /memberships/{id}/...
         */
        $membership = $this->memberships->findById(
            (int) $request->route('id')
        );

        if ($membership === null) {
            throw new MembershipNotFoundException();
        }

        return [
            'clubId' => $membership->getClubId(),
            'branchId' => $membership->getBranchId(),
        ];
    }
}
