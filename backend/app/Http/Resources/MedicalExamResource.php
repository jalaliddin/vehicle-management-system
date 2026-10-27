<?php

namespace App\Http\Resources;

use App\Enums\Permission;
use App\Models\MedicalExam;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A medical exam. Medical details are included only for users allowed to see them.
 *
 * @mixin MedicalExam
 */
class MedicalExamResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeDetails = $request->user()?->isSuperAdmin() || $request->user()?->hasPermission(Permission::OhsMedicalView);

        return array_merge([
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'type' => $this->type,
            'exam_date' => $this->exam_date->toDateString(),
            'next_due_date' => $this->dueDate()?->toDateString(),
            'status' => $this->status(),
            'employee' => $this->whenLoaded('employee', fn () => [
                'id' => $this->employee->id,
                'full_name' => $this->employee->full_name,
                'organization_id' => $this->employee->organization_id,
            ]),
        ], $canSeeDetails ? array_merge(
            $this->resource->only(array_diff(MedicalExam::MEDICAL_DETAILS, ['document_path'])),
            ['has_document' => $this->document_path !== null],
        ) : []);
    }
}
