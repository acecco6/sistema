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
            ->when(array_key_exists('active', $filters), fn(Builder $q) => $q->where('active', $filters['active']))
            ->when($filters['search'] ?? null, function (Builder $q, string $search) {
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
                $q->whereHas('customer', fn(Builder $c) => $c->where('name', 'like', "%{$escaped}%")
                    ->orWhere('email', 'like', "%{$escaped}%")->orWhere('phone', 'like', "%{$escaped}%"));
            })->orderByDesc('active')->orderByDesc('id');
        $p = $query->paginate($perPage, ['*'], 'page', $page);

        return ['items' => $p->getCollection()->map(fn($link) => $this->data($link))->all(), 'pagination' => [
            'current_page' => $p->currentPage(),
            'per_page' => $p->perPage(),
            'total' => $p->total(),
            'last_page' => $p->lastPage(),
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
                $this->linkVerifiedUser($user->id);
                $customer = Customer::query()->firstOrCreate(['user_id' => $user->id], [
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_normalized' => $this->normalizeEmail($user->email),
                    'phone' => $data['phone'] ?? null,
                    'active' => true,
                ]);
            } elseif (! empty($data['customer_id'])) {
                $customer = Customer::query()->findOrFail($data['customer_id']);
            } else {
                $normalizedEmail = $this->normalizeEmail($data['email'] ?? null);
                $customer = $normalizedEmail !== null
                    ? Customer::query()->where('email_normalized', $normalizedEmail)->lockForUpdate()->first()
                    : null;

                if ($customer === null) {
                    $customer = Customer::query()->create([
                        'name' => $data['name'],
                        'email' => $data['email'] ?? null,
                        'email_normalized' => $normalizedEmail,
                        'phone' => $data['phone'] ?? null,
                        'active' => true,
                    ]);
                }
            }

            $link = ClubCustomer::query()->firstOrCreate(['club_id' => $clubId, 'customer_id' => $customer->id], [
                'active' => true,
                'notes' => $data['notes'] ?? null,
            ]);

            if (!$link->wasRecentlyCreated) {
                throw new \Exception('El cliente ya pertenece al club.');
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
                $customerData = array_intersect_key($data, array_flip(['name', 'email', 'phone']));
                if (array_key_exists('email', $customerData)) {
                    $normalizedEmail = $this->normalizeEmail($customerData['email']);
                    $this->ensureEmailIsAvailable($normalizedEmail, $link->customer->id);
                    $customerData['email_normalized'] = $normalizedEmail;
                }
                $link->customer->update($customerData);
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
        $this->linkVerifiedUser($userId);
        $user = User::query()->findOrFail($userId);
        $customer = Customer::query()->firstOrCreate(['user_id' => $userId], [
            'name' => $user->name,
            'email' => $user->email,
            'email_normalized' => $this->normalizeEmail($user->email),
            'active' => true,
        ]);
        $link = ClubCustomer::query()->firstOrCreate(['club_id' => $clubId, 'customer_id' => $customer->id], ['active' => true]);
        return $this->data($link->load('customer.user:id,email_verified_at'));
    }

    public function linkVerifiedUser(int $userId): void
    {
        DB::transaction(function () use ($userId) {
            $user = User::query()->lockForUpdate()->findOrFail($userId);
            if (! $user->hasVerifiedEmail()) {
                throw ValidationException::withMessages(['email' => 'El email debe estar verificado para asociar el cliente.']);
            }

            if (Customer::query()->where('user_id', $user->id)->exists()) return;

            $customer = Customer::query()->where('email_normalized', $this->normalizeEmail($user->email))
                ->whereNull('user_id')->lockForUpdate()->first();

            if ($customer !== null) {
                $customer->update(['user_id' => $user->id]);
            }
        });
    }

    public function belongsToUser(int $clubCustomerId, int $userId): bool
    {
        return ClubCustomer::query()->whereKey($clubCustomerId)
            ->whereHas('customer', fn(Builder $query) => $query->where('user_id', $userId))
            ->exists();
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
        return [
            'id' => $link->id,
            'club_id' => $link->club_id,
            'customer_id' => $customer->id,
            'user_id' => $customer->user_id,
            'has_account' => $customer->user_id !== null,
            'email_verified' => $customer->user?->hasVerifiedEmail() ?? false,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'active' => (bool) $link->active,
            'notes' => $link->notes,
            'first_reservation_at' => $link->first_reservation_at?->toISOString(),
            'last_reservation_at' => $link->last_reservation_at?->toISOString(),
            'created_at' => $link->created_at?->toISOString(),
            'updated_at' => $link->updated_at?->toISOString()
        ];
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = $email !== null ? trim($email) : null;
        return $email === null || $email === '' ? null : strtolower($email);
    }

    private function ensureEmailIsAvailable(?string $normalizedEmail, ?int $exceptCustomerId = null): void
    {
        if ($normalizedEmail === null) return;
        $query = Customer::query()->where('email_normalized', $normalizedEmail);
        if ($exceptCustomerId !== null) $query->where('id', '!=', $exceptCustomerId);
        if ($query->exists()) throw ValidationException::withMessages(['email' => 'Ya existe un cliente con ese email.']);
    }
}
