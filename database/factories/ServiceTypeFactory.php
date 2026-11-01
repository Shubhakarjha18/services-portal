<?php

namespace Database\Factories;

use App\Models\ServiceType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ServiceType>
 */
class ServiceTypeFactory extends Factory
{
    protected $model = ServiceType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'department_id' => DepartmentFactory::new(),
            'name' => Str::title($name),
            'code' => strtoupper(Str::random(3).'-'.Str::random(6)),
            'description' => fake()->sentence(20),
            'fee' => fake()->randomFloat(2, 0, 200),
            'sla_days' => fake()->numberBetween(5, 45),
            'required_documents' => ['Citizen number', 'Proof of address'],
            'eligibility_notes' => ['Applicant must be a registered resident.'],
            'is_public' => true,
            'requires_payment' => fake()->boolean(70),
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_public' => false]);
    }
}
