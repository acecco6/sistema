<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'email', 'email_normalized', 'phone', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function clubCustomers(): HasMany { return $this->hasMany(ClubCustomer::class); }
}
