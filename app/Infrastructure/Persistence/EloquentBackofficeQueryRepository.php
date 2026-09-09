<?php

namespace App\Infrastructure\Persistence;

use App\Application\Backoffice\Contracts\BackofficeQueryRepository;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Reservations\Enums\ReservationStatus;
use App\Models\Branch;
use App\Models\FixedReservationConflict;
use App\Models\Membership;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\TipoCourt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class EloquentBackofficeQueryRepository implements BackofficeQueryRepository
{
    public function sessionContext(int $userId): array
    {
        $user = User::query()->findOrFail($userId);

        $memberships = Membership::query()
            ->with([
                'club:id,name,active',
                'branch:id,club_id,name,active',
                'role:id,name,description',
                'role.permissions' => fn ($query) => $query
                    ->where('permissions.active', true)
                    ->orderBy('permissions.name'),
            ])
            ->where('user_id', $userId)
            ->where('active', true)
            ->orderBy('club_id')
            ->orderByRaw('branch_id IS NOT NULL')
            ->orderBy('branch_id')
            ->get();

        $effectivePermissions = $memberships
            ->flatMap(fn (Membership $membership) => $membership->role->permissions->pluck('name'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'user' => $this->userData($user),
            'memberships' => $memberships->map(fn (Membership $membership) => [
                ...$this->membershipData($membership),
                'permissions' => $membership->role->permissions->pluck('name')->values()->all(),
            ])->all(),
            'effective_permissions' => $effectivePermissions,
        ];
    }

    public function memberships(int $clubId, array $filters, int $page, int $perPage): array
    {
        $query = Membership::query()
            ->with(['user:id,name,email,active', 'club:id,name,active', 'branch:id,club_id,name,active', 'role:id,name,description'])
            ->where('club_id', $clubId)
            ->when(array_key_exists('accessible_branch_ids', $filters), fn (Builder $query) => $query->whereIn('branch_id', $filters['accessible_branch_ids']))
            ->when(array_key_exists('active', $filters), fn (Builder $query) => $query->where('active', $filters['active']))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['role_id'] ?? null, fn (Builder $query, int $roleId) => $query->where('rol_id', $roleId))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $escaped = $this->escapeLike($search);
                $query->whereHas('user', fn (Builder $userQuery) => $userQuery
                    ->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%"));
            })
            ->orderByDesc('active')
            ->orderBy('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginated(
            $paginator->getCollection()->map(fn (Membership $membership) => $this->membershipData($membership))->all(),
            $paginator->total(),
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->lastPage(),
        );
    }

    public function membership(int $id): ?array
    {
        $membership = Membership::query()
            ->with(['user:id,name,email,active', 'club:id,name,active', 'branch:id,club_id,name,active', 'role:id,name,description'])
            ->find($id);

        return $membership ? $this->membershipData($membership) : null;
    }

    public function roles(): array
    {
        return Role::query()
            ->orderBy('id')
            ->get(['id', 'name', 'description'])
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->description,
            ])->all();
    }

    public function roleWithPermissions(int $id): ?array
    {
        $role = Role::query()
            ->with(['permissions' => fn ($query) => $query->orderBy('name')])
            ->find($id);

        if ($role === null) {
            return null;
        }

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $role->permissions->map(fn ($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'description' => $permission->description,
                'active' => (bool) $permission->active,
            ])->all(),
        ];
    }

    public function users(string $search, ?bool $active, int $page, int $perPage): array
    {
        $escaped = $this->escapeLike($search);

        $query = User::query()
            ->select(['id', 'name', 'email', 'active'])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($escaped) {
                $query->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%");
            }))
            ->when($active !== null, fn (Builder $query) => $query->where('active', $active))
            ->orderBy('name')
            ->orderBy('id');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginated(
            $paginator->getCollection()->map(fn (User $user) => $this->userData($user))->all(),
            $paginator->total(),
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->lastPage(),
        );
    }

    public function courtTypes(): array
    {
        return TipoCourt::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description'])
            ->map(fn (TipoCourt $type) => [
                'id' => $type->id,
                'name' => $type->name,
                'description' => $type->description,
            ])->all();
    }

    public function dashboard(int $branchId, string $date): array
    {
        $branch = Branch::query()->withCount(['courts as active_courts_count' => fn (Builder $query) => $query->where('active', true)])->findOrFail($branchId);

        $reservations = Reservation::query()
            ->with(['court:id,branch_id,name', 'customer:id,name,email'])
            ->whereHas('court', fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereDate('starts_at', $date)
            ->orderBy('starts_at')
            ->get();

        $reservationIds = $reservations->pluck('id');
        $approvedPayments = Payment::query()
            ->whereIn('reservation_id', $reservationIds)
            ->where('status', PaymentStatus::APPROVED->value)
            ->sum('amount');

        $pendingRefundsQuery = PaymentRefund::query()
            ->whereHas('reservation.court', fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('status', RefundStatus::PENDING->value);

        $operatingMinutes = $this->operatingMinutes($branch->opening_time, $branch->closing_time);
        $occupiedMinutes = $reservations
            ->whereIn('status', [ReservationStatus::CONFIRMED, ReservationStatus::COMPLETED])
            ->sum(fn (Reservation $reservation) => $reservation->starts_at->diffInMinutes($reservation->ends_at));
        $capacityMinutes = $operatingMinutes * (int) $branch->active_courts_count;

        $now = now();
        $upcoming = $reservations
            ->filter(fn (Reservation $reservation) => $reservation->starts_at->greaterThanOrEqualTo($now) && ! in_array($reservation->status, [ReservationStatus::CANCELLED, ReservationStatus::EXPIRED], true))
            ->take(10)
            ->map(fn (Reservation $reservation) => $this->dashboardReservation($reservation))
            ->values()
            ->all();

        return [
            'date' => $date,
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'active_courts' => (int) $branch->active_courts_count,
            ],
            'reservations' => [
                'total' => $reservations->count(),
                'by_status' => collect(ReservationStatus::cases())->mapWithKeys(fn (ReservationStatus $status) => [
                    $status->value => $reservations->where('status', $status)->count(),
                ])->all(),
            ],
            'occupancy' => [
                'occupied_minutes' => (int) $occupiedMinutes,
                'capacity_minutes' => $capacityMinutes,
                'percentage' => $capacityMinutes > 0 ? round(($occupiedMinutes / $capacityMinutes) * 100, 2) : 0.0,
            ],
            'upcoming_reservations' => $upcoming,
            'payments' => [
                'approved_amount' => number_format((float) $approvedPayments, 2, '.', ''),
                'reservation_total_amount' => number_format((float) $reservations->sum('total_price'), 2, '.', ''),
            ],
            'pending_refunds' => [
                'count' => (clone $pendingRefundsQuery)->count(),
                'amount' => number_format((float) (clone $pendingRefundsQuery)->sum('amount'), 2, '.', ''),
            ],
            'unresolved_fixed_reservation_conflicts' => FixedReservationConflict::query()
                ->where('resolved', false)
                ->whereHas('court', fn (Builder $query) => $query->where('branch_id', $branchId))
                ->count(),
        ];
    }

    private function membershipData(Membership $membership): array
    {
        return [
            'id' => $membership->id,
            'user' => $this->userData($membership->user),
            'club' => [
                'id' => $membership->club->id,
                'name' => $membership->club->name,
                'active' => (bool) $membership->club->active,
            ],
            'role' => [
                'id' => $membership->role->id,
                'name' => $membership->role->name,
                'description' => $membership->role->description,
            ],
            'branch' => $membership->branch ? [
                'id' => $membership->branch->id,
                'name' => $membership->branch->name,
                'active' => (bool) $membership->branch->active,
            ] : null,
            'scope' => $membership->branch_id === null ? 'club' : 'branch',
            'active' => (bool) $membership->active,
            'created_at' => $membership->created_at?->toDateTimeString(),
            'updated_at' => $membership->updated_at?->toDateTimeString(),
        ];
    }

    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'active' => (bool) $user->active,
        ];
    }

    private function dashboardReservation(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'court' => ['id' => $reservation->court->id, 'name' => $reservation->court->name],
            'customer' => $reservation->customer ? $this->userData($reservation->customer) : null,
            'guest' => $reservation->customer_user_id === null ? [
                'name' => $reservation->guest_name,
                'email' => $reservation->guest_email,
                'phone' => $reservation->guest_phone,
            ] : null,
            'starts_at' => $reservation->starts_at->toDateTimeString(),
            'ends_at' => $reservation->ends_at->toDateTimeString(),
            'status' => $reservation->status->value,
            'source' => $reservation->fixed_reservation_slot_id === null ? 'manual' : 'fixed_reservation',
        ];
    }

    private function operatingMinutes(string $openingTime, string $closingTime): int
    {
        $opening = now()->setTimeFromTimeString($openingTime);
        $closing = now()->setTimeFromTimeString($closingTime);

        return max(0, $opening->diffInMinutes($closing, false));
    }

    private function paginated(array $data, int $total, int $page, int $perPage, int $lastPage): array
    {
        return [
            'items' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
