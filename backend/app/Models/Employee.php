<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use Auditable, HasFactory, SoftDeletes;

    /**
     * Card fields: everything except the assignment, which only employment
     * events change.
     *
     * @var list<string>
     */
    public const CARD_FIELDS = [
        'personnel_number',
        'last_name',
        'first_name',
        'middle_name',
        'pinfl',
        'passport',
        'birth_date',
        'gender',
        'phone',
        'address',
        'education',
        'clothing_size',
        'shoe_size',
        'employment_type',
        'annual_leave_days',
        'notes',
    ];

    protected $fillable = [
        'organization_id',
        'department_id',
        'position_id',
        'personnel_number',
        'last_name',
        'first_name',
        'middle_name',
        'pinfl',
        'passport',
        'birth_date',
        'gender',
        'phone',
        'address',
        'education',
        'photo_path',
        'clothing_size',
        'shoe_size',
        'employment_type',
        'status',
        'hire_date',
        'dismissal_date',
        'annual_leave_days',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'department_id' => 'integer',
            'position_id' => 'integer',
            'birth_date' => 'date',
            'hire_date' => 'date',
            'dismissal_date' => 'date',
            'annual_leave_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $employee) {
            $employee->annual_leave_days ??= config('hr.annual_leave_days');
        });
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim(implode(' ', array_filter([$this->last_name, $this->first_name, $this->middle_name]))));
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function auditEmployeeId(): ?int
    {
        return $this->id;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(EmploymentEvent::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(EmployeeAbsence::class);
    }

    public function medicalExams(): HasMany
    {
        return $this->hasMany(MedicalExam::class);
    }

    public function briefings(): BelongsToMany
    {
        return $this->belongsToMany(SafetyBriefing::class, 'safety_briefing_participants')->withPivot('next_due_date');
    }

    /**
     * The exam that defines the employee's medical status.
     */
    public function latestMedicalExam(): HasOne
    {
        return $this->hasOne(MedicalExam::class)->ofMany(['exam_date' => 'max', 'id' => 'max']);
    }

    /**
     * Whether an actual (not planned) absence covers the date.
     */
    public function isAbsentOn(CarbonInterface $date): bool
    {
        return $this->absences()
            ->where('is_planned', false)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->exists();
    }

    /**
     * Copy the identity fields to the employee's driver profile, if any.
     */
    public function syncDriverProfile(): void
    {
        $this->driver?->update([
            'full_name' => $this->full_name,
            'pinfl' => $this->pinfl,
            'phone' => $this->phone,
            'organization_id' => $this->organization_id,
        ]);
    }
}
