<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    protected $fillable = [
        'branch_id',
        'tipo_court_id',
        'name',
        'active',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function tipo_court()
    {
        return $this->belongsTo(TipoCourt::class);
    }
}
