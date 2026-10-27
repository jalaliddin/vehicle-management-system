<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MedicalExamResource;
use App\Models\Employee;
use App\Models\MedicalExam;
use App\Support\Hr\DueDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MedicalExamController extends Controller
{
    public function index(Employee $employee): JsonResponse
    {
        Gate::authorize('viewFor', [MedicalExam::class, $employee]);

        $employee->loadMissing('position');
        $exams = $employee->medicalExams()->orderByDesc('exam_date')->orderByDesc('id')->get()
            ->each->setRelation('employee', $employee);

        return MedicalExamResource::collection($exams)->response();
    }

    /**
     * The latest exam of every active employee, optionally only those overdue or due soon.
     */
    public function organizationIndex(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', MedicalExam::class);

        $request->validate(['state' => ['nullable', 'in:valid,due_soon,overdue,unfit']]);

        $user = $request->user();

        $exams = Employee::with(['latestMedicalExam', 'position'])
            ->where('status', 'active')
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->organization_id, fn ($q) => $q->where('organization_id', $request->organization_id))
            ->whereHas('latestMedicalExam')
            ->get()
            ->map(fn (Employee $employee) => $employee->latestMedicalExam->setRelation('employee', $employee))
            ->when($request->state, fn ($exams) => $exams->filter(fn (MedicalExam $exam) => $exam->status() === $request->state))
            ->sortBy(fn (MedicalExam $exam) => $exam->dueDate()?->toDateString())
            ->values();

        return MedicalExamResource::collection($exams)->response();
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('createFor', [MedicalExam::class, $employee]);

        $validated = $this->validated($request);

        $exam = $employee->medicalExams()->create(array_merge($validated, [
            'next_due_date' => $this->nextDue($employee, $validated),
            'document_path' => $request->file('document')?->store("hr/medical/{$employee->id}", 'local'),
            'created_by' => $request->user()->id,
        ]));

        return (new MedicalExamResource($exam))->response()->setStatusCode(201);
    }

    public function update(Request $request, MedicalExam $exam): JsonResponse
    {
        Gate::authorize('update', $exam);

        $validated = $this->validated($request);
        $changes = array_merge($validated, ['next_due_date' => $this->nextDue($exam->employee, $validated)]);

        if ($request->hasFile('document')) {
            $this->deleteDocument($exam);
            $changes['document_path'] = $request->file('document')->store("hr/medical/{$exam->employee_id}", 'local');
        }

        $exam->update($changes);

        return (new MedicalExamResource($exam))->response();
    }

    public function destroy(MedicalExam $exam): JsonResponse
    {
        Gate::authorize('delete', $exam);

        $this->deleteDocument($exam);
        $exam->delete();

        return response()->json(null, 204);
    }

    public function document(MedicalExam $exam): StreamedResponse
    {
        Gate::authorize('viewDocument', $exam);

        abort_if($exam->document_path === null, 404);

        return Storage::disk('local')->download($exam->document_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', 'in:preliminary,periodic,extraordinary'],
            'exam_date' => ['required', 'date', 'before_or_equal:today'],
            'institution' => ['nullable', 'string', 'max:255'],
            'result' => ['required', 'in:fit,fit_with_restrictions,unfit'],
            'restrictions' => ['nullable', 'string'],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:exam_date'],
            'notes' => ['nullable', 'string'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        unset($validated['document']);

        return $validated;
    }

    /**
     * The date given in the request, else the position's periodicity.
     *
     * @param  array<string, mixed>  $validated
     */
    private function nextDue(Employee $employee, array $validated): ?string
    {
        if (! empty($validated['next_due_date'])) {
            return $validated['next_due_date'];
        }

        return DueDates::medicalNextDue(
            Carbon::parse($validated['exam_date']),
            $employee->position?->medical_exam_months,
            $validated['result'],
        )?->toDateString();
    }

    private function deleteDocument(MedicalExam $exam): void
    {
        if ($exam->document_path !== null) {
            Storage::disk('local')->delete($exam->document_path);
        }
    }
}
