<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MercadoPagoAccount extends Model
{
    protected $fillable = [
        'club_id',
        'mercado_pago_user_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'public_key',
        'active',
        'connected_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',

        'expires_at' => 'datetime',
        'connected_at' => 'datetime',

        'active' => 'boolean',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
