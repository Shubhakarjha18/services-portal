<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationComment>
 */
class ApplicationCommentFactory extends Factory
{
    protected $model = ApplicationComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'user_id' => UserFactory::new()->officer(),
            'body' => fake()->sentence(15),
            'is_internal' => false,
        ];
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes) => ['is_internal' => true]);
    }
}
