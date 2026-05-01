<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_id',
        'role',
        'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function mechanicWaybills(): HasMany
    {
        return $this->hasMany(Waybill::class, 'mechanic_id');
    }

    public function doctorWaybills(): HasMany
    {
        return $this->hasMany(Waybill::class, 'doctor_id');
    }

    public function approvedWaybills(): HasMany
    {
        return $this->hasMany(Waybill::class, 'approved_by');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isHqDispatcher(): bool
    {
        return in_array($this->role, ['superadmin', 'hq_dispatcher']);
    }

    public function canManageOrganization(?int $organizationId): bool
    {
        if ($this->isSuperAdmin() || $this->isHqDispatcher()) {
            return true;
        }

        return $this->organization_id === $organizationId;
    }
}
