<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\PpeIssueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpeIssue extends Model
{
    /** @use HasFactory<PpeIssueFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'employee_id',
        'ppe_item_id',
        'quantity',
        'size',
        'issued_on',
        'due_on',
        'status',
        'closed_on',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'issued_on' => 'date',
            'due_on' => 'date',
            'closed_on' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function ppeItem(): BelongsTo
    {
        return $this->belongsTo(PpeItem::class);
    }
}
