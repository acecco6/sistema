<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Payments\Entities\PaymentRefund;
use App\Domain\Payments\Enums\RefundStatus;
use App\Domain\Payments\Repositories\PaymentRefundRepository;
use App\Models\PaymentRefund as PaymentRefundModel;
use DateTimeImmutable;

final class EloquentPaymentRefundRepository implements PaymentRefundRepository
{
    public function findById(int $id): ?PaymentRefund
    {
        $model = PaymentRefundModel::query()->find($id);

        return $model
            ? $this->toDomain($model)
            : null;
    }

    public function findByIdForUpdate(int $id): ?PaymentRefund
    {
        $model = PaymentRefundModel::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();

        return $model
            ? $this->toDomain($model)
            : null;
    }

    public function findByBranch(
        int $branchId,
        ?RefundStatus $status = null,
    ): array {
        $query = PaymentRefundModel::query()
            ->whereHas(
                'reservation.court',
                fn($query) => $query->where(
                    'branch_id',
                    $branchId
                )
            )
            ->orderBy('created_at');

        if ($status !== null) {
            $query->where(
                'status',
                $status->value
            );
        }

        return $query
            ->get()
            ->map(
                fn(PaymentRefundModel $model) =>
                $this->toDomain($model)
            )
            ->all();
    }

    public function findByReservation(int $reservationId): array
    {
        return PaymentRefundModel::query()
            ->where('reservation_id', $reservationId)
            ->orderBy('created_at')
            ->get()
            ->map(
                fn(PaymentRefundModel $model): PaymentRefund =>
                $this->toDomain($model)
            )
            ->all();
    }

    public function findPending(): array
    {
        return PaymentRefundModel::query()
            ->where(
                'status',
                RefundStatus::PENDING->value
            )
            ->orderBy('created_at')
            ->get()
            ->map(
                fn(PaymentRefundModel $model): PaymentRefund =>
                $this->toDomain($model)
            )
            ->all();
    }

    public function save(PaymentRefund $refund): PaymentRefund
    {
        $model = PaymentRefundModel::query()->create([
            'reservation_id' => $refund->getReservationId(),
            'payment_id' => $refund->getPaymentId(),
            'amount' => $refund->getAmount(),
            'status' => $refund->getStatus()->value,
            'reason' => $refund->getReason(),
            'method' => $refund->getMethod()?->value,
            'notes' => $refund->getNotes(),
            'created_by_user_id' => $refund->getCreatedByUserId(),
            'completed_by_user_id' => $refund->getCompletedByUserId(),
            'completed_at' => $refund->getCompletedAt()?->format(
                'Y-m-d H:i:s'
            ),
        ]);

        return $this->toDomain($model);
    }

    public function update(PaymentRefund $refund): PaymentRefund
    {
        $model = PaymentRefundModel::query()
            ->findOrFail($refund->getId());

        $model->update([
            'reservation_id' => $refund->getReservationId(),
            'payment_id' => $refund->getPaymentId(),
            'amount' => $refund->getAmount(),
            'status' => $refund->getStatus()->value,
            'reason' => $refund->getReason(),
            'method' => $refund->getMethod()?->value,
            'notes' => $refund->getNotes(),
            'created_by_user_id' => $refund->getCreatedByUserId(),
            'completed_by_user_id' => $refund->getCompletedByUserId(),
            'completed_at' => $refund->getCompletedAt()?->format(
                'Y-m-d H:i:s'
            ),
        ]);

        $model->refresh();

        return $this->toDomain($model);
    }

    public function sumCommittedByReservation(int $reservationId): string
    {
        $sum = PaymentRefundModel::query()
            ->where('reservation_id', $reservationId)
            ->whereIn('status', [
                RefundStatus::PENDING->value,
                RefundStatus::COMPLETED->value,
            ])
            ->sum('amount');

        return bcadd(
            (string) $sum,
            '0',
            2
        );
    }

    public function sumCompletedByReservation(int $reservationId): string
    {
        $sum = PaymentRefundModel::query()
            ->where('reservation_id', $reservationId)
            ->where(
                'status',
                RefundStatus::COMPLETED->value
            )
            ->sum('amount');

        return bcadd(
            (string) $sum,
            '0',
            2
        );
    }

    private function toDomain(PaymentRefundModel $model): PaymentRefund
    {
        return new PaymentRefund(
            id: $model->id,
            reservationId: $model->reservation_id,
            paymentId: $model->payment_id,
            amount: $model->amount,
            status: $model->status,
            reason: $model->reason,
            method: $model->method,
            notes: $model->notes,
            createdByUserId: $model->created_by_user_id,
            completedByUserId: $model->completed_by_user_id,
            completedAt: $model->completed_at
                ? new DateTimeImmutable($model->completed_at->format('Y-m-d H:i:s'))
                : null,
        );
    }
}
