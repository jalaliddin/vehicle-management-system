<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    /** @use HasFactory<PositionFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'medical_exam_months',
        'briefing_months',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'medical_exam_months' => 'integer',
            'briefing_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function ppeNorms(): HasMany
    {
        return $this->hasMany(PositionPpeNorm::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
