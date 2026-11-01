<?php

namespace Database\Factories;

use App\Enums\ApplicationStatusCode;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\ApplicationTransition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationTransition>
 */
class ApplicationTransitionFactory extends Factory
{
    protected $model = ApplicationTransition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = ApplicationStatusCode::Submitted;

        return [
            'application_id' => Application::factory(),
            'from_status_id' => null,
            'to_status_id' => ApplicationStatus::firstOrCreate(
                ['code' => $code->value],
                [
                    'name' => $code->label(),
                    'sort_order' => $code->stage() * 10,
                    'color' => 'blue',
                    'is_terminal' => false,
                    'is_citizen_visible' => true,
                    'counts_as_open' => true,
                ]
            )->id,
            'performed_by' => UserFactory::new()->citizen(),
            'performed_by_name' => fake()->name(),
            'note' => fake()->sentence(8),
            'stage' => $code->stage(),
            'visible_to_citizen' => true,
            'duration_seconds' => null,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'visible_to_citizen' => false,
            'note' => 'Internal only: waiting on the registry reply.',
        ]);
    }

    public function to(ApplicationStatusCode $code): static
    {
        return $this->state(fn (array $attributes) => [
            'to_status_id' => ApplicationStatus::firstOrCreate(
                ['code' => $code->value],
                [
                    'name' => $code->label(),
                    'sort_order' => $code->stage() * 10,
                    'color' => 'gray',
                    'is_terminal' => $code->isTerminal(),
                    'is_citizen_visible' => true,
                    'counts_as_open' => ! $code->isTerminal(),
                ]
            )->id,
            'stage' => $code->stage(),
        ]);
    }
}
