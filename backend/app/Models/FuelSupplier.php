<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\FuelSupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelSupplier extends Model
{
    /** @use HasFactory<FuelSupplierFactory> */
    use Auditable, HasFactory;

    protected $fillable = ['name', 'tin', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }
}
