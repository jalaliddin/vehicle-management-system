<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\Employee;

/**
 * Writes an audit log entry whenever the model is created, updated or deleted.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (self $model) {
            AuditLog::record($model, 'created', null, $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (self $model) {
            $changes = $model->auditableAttributes($model->getChanges());

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);

            AuditLog::record($model, 'updated', $model->auditableAttributes($old), $changes);
        });

        static::deleted(function (self $model) {
            AuditLog::record($model, 'deleted', $model->auditableAttributes($model->getAttributes()));
        });
    }

    /**
     * The employee this record is about, so their history is one query.
     */
    public function auditEmployeeId(): ?int
    {
        $id = $this->getAttribute('employee_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * The organization the record belongs to — its own, or its employee's —
     * used to scope who sees the entry.
     */
    public function auditOrganizationId(): ?int
    {
        $id = $this->getAttribute('organization_id');

        if ($id === null && $this->getAttribute('employee_id') !== null) {
            $id = Employee::withTrashed()->whereKey($this->getAttribute('employee_id'))->value('organization_id');
        }

        return $id === null ? null : (int) $id;
    }

    /**
     * Drop timestamps and hidden attributes from logged values.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditableAttributes(array $attributes): array
    {
        $skipped = array_merge([$this->getCreatedAtColumn(), $this->getUpdatedAtColumn()], $this->getHidden());

        return array_diff_key($attributes, array_flip(array_filter($skipped)));
    }
}
