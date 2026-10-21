<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PpeItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PpeItem extends Model
{
    /** @use HasFactory<PpeItemFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'kind',
        'unit',
        'default_wear_months',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_wear_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function norms(): HasMany
    {
        return $this->hasMany(PositionPpeNorm::class);
    }
}
