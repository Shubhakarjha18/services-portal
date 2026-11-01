<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name).' office',
            'code' => strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
            'description' => fake()->sentence(12),
            'contact_email' => fake()->unique()->companyEmail(),
            'default_sla_days' => fake()->numberBetween(5, 45),
            'is_active' => true,
        ];
    }
}
