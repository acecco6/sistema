<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReservationPriceSegment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reservation_id',
        'starts_at',
        'ends_at',
        'hourly_price',
        'subtotal',
        'court_price_rule_id',
        'rule_name',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',

            'hourly_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(
            Reservation::class
        );
    }

    public function courtPriceRule(): BelongsTo
    {
        return $this->belongsTo(
            CourtPriceRule::class
        );
    }
}
