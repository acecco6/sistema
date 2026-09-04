<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Payments\Entities\Payment as PaymentEntity;
use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Repositories\PaymentRepository;
use App\Models\Payment as PaymentModel;
use DateTimeImmutable;

final class EloquentPaymentRepository implements PaymentRepository
{
    public function findById(int $id): ?PaymentEntity
    {
        $model = PaymentModel::query()->find($id);

        return $model
            ? $this->toDomain($model)
            : null;
    }

    public function findByExternalReference(string $externalReference): ?PaymentEntity
    {
        $model = PaymentModel::query()
            ->where('external_reference', $externalReference)
            ->first();

        return $model
            ? $this->toDomain($model)
            : null;
    }

    public function findByProviderPaymentId(string $providerPaymentId): ?PaymentEntity
    {
        $model = PaymentModel::query()
            ->where('provider_payment_id', $providerPaymentId)
            ->first();

        return $model
            ? $this->toDomain($model)
            : null;
    }

    public function findByReservation(int $reservationId): array
    {
        return PaymentModel::query()
            ->where('reservation_id', $reservationId)
            ->orderBy('created_at')
            ->get()
            ->map(
                fn(PaymentModel $model) => $this->toDomain($model)
            )
            ->all();
    }

    public function save(PaymentEntity $payment): PaymentEntity
    {
        $model = PaymentModel::query()->create([
            'reservation_id' => $payment->getReservationId(),
            'amount' => $payment->getAmount(),
            'method' => $payment->getMethod()->value,
            'status' => $payment->getStatus()->value,
            'provider' => $payment->getProvider(),
            'provider_preference_id' => $payment->getProviderPreferenceId(),
            'provider_payment_id' => $payment->getProviderPaymentId(),
            'external_reference' => $payment->getExternalReference(),
            'checkout_url' => $payment->getCheckoutUrl(),
            'created_by_user_id' => $payment->getCreatedByUserId(),
            'paid_at' => $payment->getPaidAt(),
        ]);

        return $this->toDomain($model);
    }

    public function update(PaymentEntity $payment): PaymentEntity
    {
        $model = PaymentModel::query()
            ->findOrFail($payment->getId());

        $model->update([
            'reservation_id' => $payment->getReservationId(),
            'amount' => $payment->getAmount(),
            'method' => $payment->getMethod()->value,
            'status' => $payment->getStatus()->value,
            'provider' => $payment->getProvider(),
            'provider_preference_id' => $payment->getProviderPreferenceId(),
            'provider_payment_id' => $payment->getProviderPaymentId(),
            'external_reference' => $payment->getExternalReference(),
            'checkout_url' => $payment->getCheckoutUrl(),
            'created_by_user_id' => $payment->getCreatedByUserId(),
            'paid_at' => $payment->getPaidAt(),
        ]);

        $model->refresh();

        return $this->toDomain($model);
    }

    public function sumApprovedByReservation(int $reservationId): string
    {
        $sum = PaymentModel::query()
            ->where('reservation_id', $reservationId)
            ->where('status', PaymentStatus::APPROVED->value)
            ->sum('amount');

        return bcadd((string) $sum, '0', 2);
    }

    public function findPendingByReservation(
        int $reservationId
    ): ?PaymentEntity {
        $model = PaymentModel::query()
            ->where('reservation_id', $reservationId)
            ->where(
                'status',
                PaymentStatus::PENDING->value
            )
            ->latest('id')
            ->first();

        return $model
            ? $this->toDomain($model)
            : null;
    }


    private function toDomain(PaymentModel $model): PaymentEntity
    {
        return new PaymentEntity(
            id: $model->id,
            reservationId: $model->reservation_id,
            amount: (string) $model->amount,
            method: PaymentMethod::from($model->method),
            status: PaymentStatus::from($model->status),
            provider: $model->provider,
            providerPreferenceId: $model->provider_preference_id,
            providerPaymentId: $model->provider_payment_id,
            externalReference: $model->external_reference,
            checkoutUrl: $model->checkout_url,
            createdByUserId: $model->created_by_user_id,
            paidAt: $model->paid_at
                ? new DateTimeImmutable(
                    $model->paid_at->format('Y-m-d H:i:s')
                )
                : null,
        );
    }
}
