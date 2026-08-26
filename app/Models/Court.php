<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasFactory;

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

    public function tipoCourt()
    {
        return $this->belongsTo(TipoCourt::class);
    }
}
