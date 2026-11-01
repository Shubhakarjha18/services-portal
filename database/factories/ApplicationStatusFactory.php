<?php

namespace Database\Factories;

use App\Enums\ApplicationStatusCode;
use App\Models\ApplicationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationStatus>
 */
class ApplicationStatusFactory extends Factory
{
    protected $model = ApplicationStatus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = fake()->randomElement(ApplicationStatusCode::cases());

        return [
            'code' => $code->value,
            'name' => $code->label(),
            'description' => fake()->sentence(10),
            'sort_order' => $code->stage() * 10,
            'color' => 'gray',
            'is_terminal' => $code->isTerminal(),
            'is_citizen_visible' => true,
            'counts_as_open' => ! $code->isTerminal(),
        ];
    }

    public function open(string $code = ApplicationStatusCode::Submitted->value): static
    {
        return $this->forCode($code, false);
    }

    public function terminal(string $code = ApplicationStatusCode::Completed->value): static
    {
        return $this->forCode($code, true);
    }

    private function forCode(string $code, bool $terminal): static
    {
        $enum = ApplicationStatusCode::from($code);

        return $this->state(fn (array $attributes) => [
            'code' => $enum->value,
            'name' => $enum->label(),
            'is_terminal' => $terminal,
            'counts_as_open' => ! $terminal,
            'sort_order' => $enum->stage() * 10,
        ]);
    }
}
