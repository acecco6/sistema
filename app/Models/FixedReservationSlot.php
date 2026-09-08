<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FixedReservationSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixed_reservation_id',
        'court_id',
        'day_of_week',
        'start_time',
        'duration_minutes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'duration_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function fixedReservation(): BelongsTo
    {
        return $this->belongsTo(
            FixedReservation::class
        );
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(
            Court::class
        );
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(
            Reservation::class,
            'fixed_reservation_slot_id'
        );
    }
}
