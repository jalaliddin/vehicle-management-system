<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionPpeNorm extends Model
{
    use Auditable;

    protected $fillable = [
        'position_id',
        'ppe_item_id',
        'quantity',
        'wear_months',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'wear_months' => 'integer',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function ppeItem(): BelongsTo
    {
        return $this->belongsTo(PpeItem::class);
    }
}
