<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentMethod;
use App\Domain\Payments\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PaymentRefund extends Model
{
    use HasFactory;

    protected $table = 'payment_refunds';

    protected $fillable = [
        'reservation_id',
        'payment_id',
        'amount',
        'status',
        'reason',
        'method',
        'notes',
        'created_by_user_id',
        'completed_by_user_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => RefundStatus::class,
            'method' => PaymentMethod::class,
            'completed_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(
            Reservation::class,
            'reservation_id'
        );
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(
            Payment::class,
            'payment_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'completed_by_user_id'
        );
    }
}
