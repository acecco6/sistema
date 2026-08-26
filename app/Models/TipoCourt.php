<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoCourt extends Model
{
    use HasFactory;

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
