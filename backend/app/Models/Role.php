<?php

namespace App\Models;

use App\Enums\Permission;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public const SUPER_ADMIN = 'superadmin';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SUPER_ADMIN;
    }

    /**
     * The permission keys this role grants, in catalog order. Super Admin
     * implicitly holds all of them; keys no longer in the Permission enum
     * are ignored.
     *
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::values();
        }

        return array_values(array_intersect(Permission::values(), $this->permissions->pluck('permission')->all()));
    }

    /**
     * @param  list<string>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        $permissions = array_values(array_unique($permissions));

        $this->permissions()->whereNotIn('permission', $permissions)->delete();

        $existing = $this->permissions()->pluck('permission')->all();

        $this->permissions()->createMany(array_map(
            fn (string $permission) => ['permission' => $permission],
            array_values(array_diff($permissions, $existing)),
        ));

        $this->unsetRelation('permissions');
    }
}
