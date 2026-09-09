<?php

namespace App\Infrastructure\Persistence;

use App\Application\Customers\Contracts\CustomerRepository;
use App\Models\ClubCustomer;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EloquentCustomerRepository implements CustomerRepository
{
    public function paginateForClub(int $clubId, array $filters, int $page, int $perPage): array
    {
        $query = ClubCustomer::query()->with('customer.user:id,email_verified_at')->where('club_id', $clubId)
            ->when(array_key_exists('active', $filters), fn (Builder $q) => $q->where('active', $filters['active']))
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $q->whereHas('customer', fn (Builder $c) => $c->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%")->orWhere('phone', 'like', "%{$escaped}%"));
            })->orderByDesc('active')->orderByDesc('id');
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        return ['items' => $p->getCollection()->map(fn ($link) => $this->data($link))->all(), 'pagination' => [
            'current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage(),
        ]];
    }

    public function findClubCustomer(int $clubId, int $clubCustomerId): ?array
    {
        $link = ClubCustomer::query()->with('customer.user:id,email_verified_at')->where('club_id', $clubId)->find($clubCustomerId);
        return $link ? $this->data($link) : null;
    }

    public function createOrAttach(int $clubId, array $data): array
    {
        return DB::transaction(function () use ($clubId, $data) {
            if (! empty($data['user_id'])) {
                $user = User::query()->findOrFail($data['user_id']);
                if (! $user->hasVerifiedEmail()) {
                    throw ValidationException::withMessages(['user_id' => 'La cuenta debe tener el email verificado.']);
                }
                $customer = Customer::query()->firstOrCreate(['user_id' => $user->id], [
                    'name' => $user->name, 'email' => $user->email, 'phone' => $data['phone'] ?? null, 'active' => true,
                ]);
            } elseif (! empty($data['customer_id'])) {
                $customer = Customer::query()->findOrFail($data['customer_id']);
            } else {
                $customer = Customer::query()->create([
                    'name' => $data['name'], 'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null, 'active' => true,
                ]);
            }

            $link = ClubCustomer::query()->firstOrCreate(['club_id' => $clubId, 'customer_id' => $customer->id], [
                'active' => true, 'notes' => $data['notes'] ?? null,
            ]);
            if (! $link->wasRecentlyCreated) {
                throw ValidationException::withMessages(['customer_id' => 'El cliente ya pertenece al club.']);
            }
            return $this->data($link->load('customer.user:id,email_verified_at'));
        });
    }

    public function update(int $clubId, int $clubCustomerId, array $data): ?array
    {
        $link = ClubCustomer::query()->with('customer')->where('club_id', $clubId)->find($clubCustomerId);
        if (! $link) return null;
        DB::transaction(function () use ($link, $data) {
            if ($link->customer->user_id !== null && array_intersect(array_keys($data), ['name', 'email', 'phone']) !== []) {
                throw ValidationException::withMessages(['customer' => 'Los datos de identidad de un cliente con cuenta se modifican desde su perfil.']);
            }
            if ($link->customer->user_id === null) {
                $link->customer->update(array_intersect_key($data, array_flip(['name', 'email', 'phone'])));
            }
            if (array_key_exists('notes', $data)) $link->update(['notes' => $data['notes']]);
        });
        return $this->data($link->fresh(['customer.user:id,email_verified_at']));
    }

    public function changeStatus(int $clubId, int $clubCustomerId, bool $active): ?array
    {
        $link = ClubCustomer::query()->where('club_id', $clubId)->find($clubCustomerId);
        if (! $link) return null;
        $link->update(['active' => $active]);
        return $this->data($link->load('customer.user:id,email_verified_at'));
    }

    public function ensureForVerifiedUser(int $clubId, int $userId): array
    {
        $user = User::query()->findOrFail($userId);
        if (! $user->hasVerifiedEmail()) throw ValidationException::withMessages(['email' => 'Debés verificar tu email antes de reservar.']);
        $customer = Customer::query()->firstOrCreate(['user_id' => $userId], ['name' => $user->name, 'email' => $user->email, 'active' => true]);
        $link = ClubCustomer::query()->firstOrCreate(['club_id' => $clubId, 'customer_id' => $customer->id], ['active' => true]);
        return $this->data($link->load('customer.user:id,email_verified_at'));
    }

    public function recordReservation(int $clubCustomerId, string $reservedAt): void
    {
        $link = ClubCustomer::query()->findOrFail($clubCustomerId);
        $link->update([
            'first_reservation_at' => $link->first_reservation_at ?? $reservedAt,
            'last_reservation_at' => $reservedAt,
        ]);
    }

    private function data(ClubCustomer $link): array
    {
        $customer = $link->customer;
        return ['id' => $link->id, 'club_id' => $link->club_id, 'customer_id' => $customer->id,
            'user_id' => $customer->user_id, 'has_account' => $customer->user_id !== null,
            'email_verified' => $customer->user?->hasVerifiedEmail() ?? false,
            'name' => $customer->name, 'email' => $customer->email, 'phone' => $customer->phone,
            'active' => (bool) $link->active, 'notes' => $link->notes,
            'first_reservation_at' => $link->first_reservation_at?->toISOString(), 'last_reservation_at' => $link->last_reservation_at?->toISOString(),
            'created_at' => $link->created_at?->toISOString(), 'updated_at' => $link->updated_at?->toISOString()];
    }
}
