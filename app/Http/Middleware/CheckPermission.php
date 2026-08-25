<?php

namespace App\Http\Middleware;

use App\Application\Authorization\AuthorizationService;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Memberships\Exceptions\MembershipNotFoundException;
use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Shared\Exceptions\AuthorizationDeniedException;
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

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            throw new RuntimeException(
                'La ruta no tiene un nombre definido.'
            );
        }

        /*
     * Rutas de colección
     */
        if (str_ends_with($routeName, '.collection')) {
            $this->authorizeCollection(
                request: $request,
                userId: $user->id,
                routeName: $routeName,
            );

            return $next($request);
        }

        /*
     * Caso especial de lectura de Club.
     *
     * Una membership limitada a una branch puede ver
     * información general de su club.
     */
        if (
            $routeName === 'club.view'
            && $request->route('id') !== null
        ) {
            $this->authorization->authorizeInClub(
                userId: $user->id,
                clubId: (int) $request->route('id'),
                permission: $routeName,
            );

            return $next($request);
        }

        [$resource] = explode('.', $routeName);

        $scope = match ($resource) {
            'club' => $this->resolveClubScope($request),
            'branch' => $this->resolveBranchScope($request),
            'membership' => $this->resolveMembershipScope($request),

            default => throw new RuntimeException(
                "No existe un resolver para [{$resource}]."
            ),
        };

        $this->authorization->authorize(
            userId: $user->id,
            clubId: $scope['clubId'],
            branchId: $scope['branchId'],
            permission: $routeName,
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

    private function authorizeCollection(
        Request $request,
        int $userId,
        string $routeName,
    ): void {
        [$resource] = explode('.', $routeName);

        match ($resource) {
            'club' => $this->authorizeClubCollection(
                userId: $userId,
            ),

            'branch' => $this->authorizeBranchCollection(
                request: $request,
                userId: $userId,
            ),

            default => throw new RuntimeException(
                "No existe autorización de colección para [{$resource}]."
            ),
        };
    }

    private function authorizeClubCollection(int $userId): void
    {
        if (! $this->memberships->hasActiveMemberships($userId)) {
            throw new AuthorizationDeniedException();
        }
    }

    private function authorizeBranchCollection(Request $request, int $userId,): void
    {
        $clubId = $request->route('club_id');

        if ($clubId === null) {
            throw new RuntimeException(
                'No se pudo determinar el club.'
            );
        }

        $membership = $this->memberships->findActiveForClub(
            userId: $userId,
            clubId: (int) $clubId,
        );

        if ($membership === null) {
            throw new AuthorizationDeniedException();
        }
    }
}
