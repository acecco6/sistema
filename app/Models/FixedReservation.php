<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FixedReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'club_id',
        'customer_user_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'created_by_user_id',
        'starts_on',
        'ends_on',
        'active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'active' => 'boolean',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(
            Club::class
        );
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

    public function slots(): HasMany
    {
        return $this->hasMany(
            FixedReservationSlot::class
        );
    }
}
