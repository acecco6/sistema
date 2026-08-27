<?php

namespace App\Http\Middleware;

use App\Application\Authorization\AuthorizationService;
use App\Domain\Branches\Exceptions\BranchNotFoundException;
use App\Domain\Branches\Repositories\BranchRepository;
use App\Domain\Courts\Exceptions\CourtNotFoundException;
use App\Domain\Courts\Repositories\CourtRepository;
use App\Domain\Memberships\Exceptions\MembershipNotFoundException;
use App\Domain\Memberships\Repositories\MembershipRepository;
use App\Domain\Pricing\Exceptions\CourtPriceNotFoundException;
use App\Domain\Pricing\Exceptions\CourtPriceRuleNotFoundException;
use App\Domain\Pricing\Repositories\CourtPriceRepository;
use App\Domain\Reservations\Exceptions\ReservationNotFoundException;
use App\Domain\Reservations\Repositories\ReservationRepository;
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
        private CourtRepository $courts,
        private CourtPriceRepository $prices,
        private ReservationRepository $reservations,
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
            'court' => $this->resolveCourtScope($request),
            'court_price' => $this->resolveCourtPriceScope($request),
            'court_promotion' => $this->resolveCourtPromotionScope($request),
            'reservation' => $this->resolveReservationScope($request),
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

    private function resolveCourtScope(Request $request): array
    {
        /*
         * POST /branches/{branch_id}/courts
         */
        $branchId = $request->route('branch_id');
        if ($branchId !== null) {
            $branch = $this->branches->findById((int) $branchId);
            if ($branch === null) {
                throw new BranchNotFoundException();
            }

            return [
                'clubId' => $branch->getClubId(),
                'branchId' => $branch->getId(),
            ];
        }

        /*
         * GET, PUT, DELETE /courts/{id}
         */
        $courtId = $request->route('id');
        if ($courtId === null) {
            throw new RuntimeException('No se pudo determinar la cancha.');
        }

        $court = $this->courts->findById((int) $courtId);
        if ($court === null) {
            throw new CourtNotFoundException((int) $courtId);
        }

        $branch = $this->branches->findById($court->getBranchId());
        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        return [
            'clubId' => $branch->getClubId(),
            'branchId' => $branch->getId(),
        ];
    }

    private function authorizeCollection(Request $request, int $userId, string $routeName): void
    {
        [$resource] = explode('.', $routeName);

        match ($resource) {
            'club' => $this->authorizeClubCollection(
                userId: $userId,
            ),

            'branch' => $this->authorizeBranchCollection(
                request: $request,
                userId: $userId,
            ),

            'court' => $this->authorizeCourtCollection(
                request: $request,
                userId: $userId,
            ),

            'court_price' => $this->authorizeCourtPriceCollection($request, $userId),

            'court_promotion' => $this->authorizeCourtPromotionCollection($request, $userId),

            'reservation' => $this->authorizeReservationCollection($request, $userId),

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

        $memberships = $this->memberships->findActiveForClub(
            userId: $userId,
            clubId: (int) $clubId,
        );

        if ($memberships === []) {
            throw new AuthorizationDeniedException();
        }
    }

    private function authorizeCourtCollection(Request $request, int $userId): void
    {
        $branchId = $request->route('branch_id');

        if ($branchId === null) {
            throw new RuntimeException('No se pudo determinar la sucursal para listar canchas.');
        }

        $branch = $this->branches->findById((int) $branchId);

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        // Verifica membresía para el branch (ya sea global o específica del branch)
        $membership = $this->memberships->findActiveForScope(
            userId: $userId,
            clubId: $branch->getClubId(),
            branchId: $branch->getId()
        );

        if ($membership === null) {
            throw new AuthorizationDeniedException();
        }
    }

    private function resolveCourtPriceScope(
        Request $request
    ): array {
        /*
     * CREATE:
     *
     * POST /branches/{branch_id}/prices
     */
        $branchId = $request->route('branch_id');

        if ($branchId !== null) {
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

        /*
     * VIEW / UPDATE / STATUS:
     *
     * /court_prices/{id}
     */
        $priceId = $request->route('id');

        if ($priceId === null) {
            throw new RuntimeException(
                'No se pudo determinar el precio.'
            );
        }

        $price = $this->prices->findById(
            (int) $priceId
        );

        if ($price === null) {
            throw new CourtPriceNotFoundException();
        }

        $branch = $this->branches->findById(
            $price->getBranchId()
        );

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        return [
            'clubId' => $branch->getClubId(),
            'branchId' => $branch->getId(),
        ];
    }


    private function resolveCourtPromotionScope(
        Request $request
    ): array {
        /*
     * CREATE:
     *
     * POST /court_prices/{court_price_id}/promotions
     */
        $courtPriceId = $request->route(
            'court_price_id'
        );

        if ($courtPriceId !== null) {
            $price = $this->prices->findById(
                (int) $courtPriceId
            );
        } else {
            /*
         * VIEW / UPDATE / STATUS:
         *
         * /court_promotions/{id}
         */
            $promotionId = $request->route('id');

            if ($promotionId === null) {
                throw new RuntimeException(
                    'No se pudo determinar la promoción.'
                );
            }

            $promotion = $this->prices->findRuleById(
                (int) $promotionId
            );

            if ($promotion === null) {
                throw new CourtPriceRuleNotFoundException();
            }

            $price = $this->prices->findById(
                $promotion->getCourtPriceId()
            );
        }

        if ($price === null) {
            throw new CourtPriceNotFoundException();
        }

        $branch = $this->branches->findById(
            $price->getBranchId()
        );

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        return [
            'clubId' => $branch->getClubId(),
            'branchId' => $branch->getId(),
        ];
    }

    private function authorizeCourtPriceCollection(
        Request $request,
        int $userId
    ): void {
        $branchId = $request->route('branch_id');

        if ($branchId === null) {
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

        $membership = $this->memberships
            ->findActiveForScope(
                userId: $userId,
                clubId: $branch->getClubId(),
                branchId: $branch->getId(),
            );

        if ($membership === null) {
            throw new AuthorizationDeniedException();
        }
    }

    private function authorizeCourtPromotionCollection(
        Request $request,
        int $userId
    ): void {
        $courtPriceId = $request->route(
            'court_price_id'
        );

        if ($courtPriceId === null) {
            throw new RuntimeException(
                'No se pudo determinar el precio.'
            );
        }

        $price = $this->prices->findById(
            (int) $courtPriceId
        );

        if ($price === null) {
            throw new CourtPriceNotFoundException();
        }

        $branch = $this->branches->findById(
            $price->getBranchId()
        );

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        $membership = $this->memberships
            ->findActiveForScope(
                userId: $userId,
                clubId: $branch->getClubId(),
                branchId: $branch->getId(),
            );

        if ($membership === null) {
            throw new AuthorizationDeniedException();
        }
    }

    private function resolveReservationScope(Request $request): array
    {
        /*
        |--------------------------------------------------------------------------
        | CREATE
        |--------------------------------------------------------------------------
        |
        | POST /courts/{court_id}/reservations
        |
        | Todavía no existe Reservation.
        | El scope se obtiene desde la Court.
        |
        */

        $courtId = $request->route('court_id');

        if ($courtId !== null) {
            $court = $this->courts->findById(
                (int) $courtId
            );

            if ($court === null) {
                throw new CourtNotFoundException();
            }

            $branch = $this->branches->findById(
                $court->getBranchId()
            );

            if ($branch === null) {
                throw new BranchNotFoundException();
            }

            return [
                'clubId' => $branch->getClubId(),
                'branchId' => $branch->getId(),
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | VIEW / CANCEL
        |--------------------------------------------------------------------------
        |
        | GET   /reservations/{id}
        | PATCH /reservations/{id}/cancel
        |
        | La Reservation ya existe.
        |
        */

        $reservationId = $request->route('id');

        if ($reservationId === null) {
            throw new RuntimeException(
                'No se pudo determinar la reserva.'
            );
        }

        $reservation = $this->reservations->findById(
            (int) $reservationId
        );

        if ($reservation === null) {
            throw new ReservationNotFoundException();
        }

        $court = $this->courts->findById(
            $reservation->getCourtId()
        );

        if ($court === null) {
            throw new CourtNotFoundException();
        }

        $branch = $this->branches->findById(
            $court->getBranchId()
        );

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        return [
            'clubId' => $branch->getClubId(),
            'branchId' => $branch->getId(),
        ];
    }

    private function authorizeReservationCollection(Request $request, int $userId): void
    {
        /*
        * GET /courts/{court_id}/reservations
        */

        $courtId = $request->route('court_id');

        if ($courtId === null) {
            throw new \RuntimeException(
                'No se pudo determinar la cancha.'
            );
        }

        $court = $this->courts->findById(
            (int) $courtId
        );

        if ($court === null) {
            throw new CourtNotFoundException();
        }

        $branch = $this->branches->findById(
            $court->getBranchId()
        );

        if ($branch === null) {
            throw new BranchNotFoundException();
        }

        /*
        * Collection no busca reservation.collection
        * como permiso real.
        *
        * Solamente valida que el usuario tenga scope
        * sobre esta Branch.
        */
        $membership = $this->memberships
            ->findActiveForScope(
                userId: $userId,
                clubId: $branch->getClubId(),
                branchId: $branch->getId(),
            );

        if ($membership === null) {
            throw new AuthorizationDeniedException();
        }
    }
}
