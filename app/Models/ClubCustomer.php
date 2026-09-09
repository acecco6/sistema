<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ClubCustomer extends Model
{
    use HasFactory;

    protected $fillable = ['club_id', 'customer_id', 'active', 'notes', 'first_reservation_at', 'last_reservation_at'];
    protected $casts = ['active' => 'boolean', 'first_reservation_at' => 'datetime', 'last_reservation_at' => 'datetime'];

    public function club(): BelongsTo { return $this->belongsTo(Club::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function reservations(): HasMany { return $this->hasMany(Reservation::class); }
    public function fixedReservations(): HasMany { return $this->hasMany(FixedReservation::class); }
}
