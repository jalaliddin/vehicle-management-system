<?php

namespace App\Http\Resources;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full employee card, with unmasked personal data.
 *
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'full_name' => $this->full_name,
            'last_name' => $this->last_name,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'personnel_number' => $this->personnel_number,
            'pinfl' => $this->pinfl,
            'passport' => $this->passport,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender' => $this->gender,
            'phone' => $this->phone,
            'address' => $this->address,
            'education' => $this->education,
            'has_photo' => $this->photo_path !== null,
            'clothing_size' => $this->clothing_size,
            'shoe_size' => $this->shoe_size,
            'employment_type' => $this->employment_type,
            'status' => $this->status,
            'hire_date' => $this->hire_date?->toDateString(),
            'dismissal_date' => $this->dismissal_date?->toDateString(),
            'annual_leave_days' => $this->annual_leave_days,
            'notes' => $this->notes,
            'organization' => $this->whenLoaded('organization', fn () => $this->organization->only(['id', 'name', 'short_name'])),
            'department' => $this->whenLoaded('department', fn () => $this->department?->only(['id', 'name'])),
            'position' => $this->whenLoaded('position', fn () => $this->position?->only(['id', 'name', 'medical_exam_months', 'briefing_months'])),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver?->only(['id', 'license_number', 'license_category', 'license_expiry', 'vehicle_id', 'status'])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
