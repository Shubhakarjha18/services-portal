<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+#?## ### ###'),
            'national_id' => fake()->unique()->numerify('##########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role_id' => $this->roleId(RoleName::Public),
            'department_id' => null,
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $this->roleId(RoleName::Admin),
            'department_id' => Department::factory(),
        ]);
    }

    public function officer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $this->roleId(RoleName::Officer),
            'department_id' => Department::factory(),
        ]);
    }

    public function citizen(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $this->roleId(RoleName::Public),
            'department_id' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Roles are seeded in production but not necessarily in a test database,
     * so ensure the row exists before referencing it.
     */
    protected function roleId(RoleName $role): int
    {
        return Role::firstOrCreate(
            ['name' => $role->value],
            ['display_name' => $role->label(), 'level' => 10, 'is_system' => true]
        )->id;
    }
}
