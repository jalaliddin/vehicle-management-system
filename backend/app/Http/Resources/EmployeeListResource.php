<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee row in a list: identity documents are masked.
 *
 * @mixin Employee
 */
class EmployeeListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'personnel_number' => $this->personnel_number,
            'pinfl' => self::mask($this->pinfl, 3, 3),
            'passport' => self::mask($this->passport, 2, 2),
            'phone' => $this->phone,
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'hire_date' => $this->hire_date?->toDateString(),
            'organization' => $this->whenLoaded('organization', fn () => $this->organization->only(['id', 'name', 'short_name'])),
            'department' => $this->whenLoaded('department', fn () => $this->department?->only(['id', 'name'])),
            'position' => $this->whenLoaded('position', fn () => $this->position?->only(['id', 'name'])),
        ];
    }

    /**
     * Keep the first and last characters, star the rest.
     */
    public static function mask(?string $value, int $keepStart, int $keepEnd): ?string
    {
        if ($value === null || mb_strlen($value) <= $keepStart + $keepEnd) {
            return $value;
        }

        return mb_substr($value, 0, $keepStart)
            .str_repeat('*', mb_strlen($value) - $keepStart - $keepEnd)
            .mb_substr($value, -$keepEnd);
    }
}
