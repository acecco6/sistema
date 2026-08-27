<?php

namespace App\Models;

use App\Domain\Reservations\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'court_id',
        'customer_user_id',
        'created_by_user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'starts_at',
        'ends_at',
        'total_price',
        'status',
        'public_token',
        'notes',
        'cancelled_at',
        'expires_at'
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expires_at' => 'datetime',
            'total_price' => 'decimal:2',
            'status' => ReservationStatus::class,
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'customer_user_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id'
        );
    }

    public function priceSegments(): HasMany
    {
        return $this->hasMany(
            ReservationPriceSegment::class
        );
    }
}
