<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CourtPriceRule extends Model
{
    protected $fillable = [
        'court_price_id',
        'name',
        'price',
        'day_of_week',
        'specific_date',
        'start_time',
        'end_time',
        'priority',
        'starts_at',
        'ends_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'day_of_week' => 'integer',
            'specific_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'priority' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function courtPrice(): BelongsTo
    {
        return $this->belongsTo(CourtPrice::class);
    }
}
