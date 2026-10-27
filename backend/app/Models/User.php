<?php

namespace App\Models;

use App\Enums\Permission;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'organization_id',
        'role_id',
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

    public function fuelStations(): BelongsToMany
    {
        return $this->belongsToMany(FuelStation::class, 'fuel_station_user');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->isSuperAdmin() ?? false;
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return $this->role?->permissionKeys() ?? [];
    }

    public function hasPermission(Permission $permission): bool
    {
        return in_array($permission->value, $this->permissionKeys(), true);
    }

    public function hasAnyPermission(Permission ...$permissions): bool
    {
        return collect($permissions)->contains(fn (Permission $permission) => $this->hasPermission($permission));
    }

    /**
     * Whether every permission of the given role is also held by this user, so they
     * can hand it out without granting more than they have.
     */
    public function holdsAllPermissionsOf(Role $role): bool
    {
        if ($role->isSuperAdmin()) {
            return $this->isSuperAdmin();
        }

        return array_diff($role->permissionKeys(), $this->permissionKeys()) === [];
    }

    public function canAccessAllOrganizations(): bool
    {
        return $this->hasPermission(Permission::AllOrganizations);
    }

    /**
     * Users without the all-organizations permission are limited to records
     * of their own organization.
     */
    public function canAccessOrganization(?int $organizationId): bool
    {
        if ($this->canAccessAllOrganizations()) {
            return true;
        }

        return $organizationId !== null && (int) $this->organization_id === $organizationId;
    }
}
