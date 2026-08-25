<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCourt extends Model
{
    protected $table = 'tipos_court';

    protected $fillable = [
        'name',
        'description',
    ];

    public function courts()
    {
        return $this->hasMany(Court::class);
    }
}
