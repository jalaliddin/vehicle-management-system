<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name, '_'),
            'description' => null,
            'is_system' => false,
        ];
    }

    /**
     * Grant the role exactly these permissions.
     */
    public function withPermissions(Permission ...$permissions): static
    {
        return $this->afterCreating(fn (Role $role) => $role->syncPermissions(
            array_map(fn (Permission $permission) => $permission->value, $permissions),
        ));
    }
}
