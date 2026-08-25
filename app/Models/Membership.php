<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use HasFactory;
    protected $table = 'memberships';

    protected $fillable = [
        'user_id',
        'club_id',
        'rol_id',
        'branch_id',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'branch_id' => 'integer',
            'user_id' => 'integer',
            'club_id' => 'integer',
            'rol_id' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function club()
    {
        return $this->belongsTo(Club::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'rol_id', 'id');
    }
}
