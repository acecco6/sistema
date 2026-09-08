<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FixedReservationConflict extends Model
{
    protected $fillable = [
        'fixed_reservation_id',
        'fixed_reservation_slot_id',
        'court_id',
        'recurrence_date',
        'starts_at',
        'ends_at',
        'reason',
        'message',
        'resolved',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'recurrence_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',

            'resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function fixedReservation(): BelongsTo
    {
        return $this->belongsTo(
            FixedReservation::class
        );
    }

    public function fixedReservationSlot(): BelongsTo
    {
        return $this->belongsTo(
            FixedReservationSlot::class
        );
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(
            Court::class
        );
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'resolved_by_user_id'
        );
    }
}
