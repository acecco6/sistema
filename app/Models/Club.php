<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $fillable = ['name', 'active'];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
