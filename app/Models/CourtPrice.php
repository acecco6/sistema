<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CourtPrice extends Model
{
    use HasFactory;
    protected $fillable = [
        'branch_id',
        'tipo_court_id',
        'price',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function tipoCourt(): BelongsTo
    {
        return $this->belongsTo(TipoCourt::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CourtPriceRule::class);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
