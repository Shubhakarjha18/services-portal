<?php

namespace Database\Factories;

use App\Enums\ApplicationStatusCode;
use App\Enums\Priority;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $submittedAt = fake()->dateTimeBetween('-40 days', 'now');
        $service = ServiceType::factory()->create();

        return [
            'reference_number' => sprintf(
                '%s-%s-%06d',
                config('portal.reference.prefix', 'PSP'),
                date('Y', $submittedAt->getTimestamp()),
                fake()->unique()->numerify('######')
            ),
            'service_type_id' => $service->id,
            'department_id' => $service->department_id,
            'citizen_id' => User::factory()->citizen(),
            'assigned_officer_id' => null,
            'application_status_id' => $this->statusId(ApplicationStatusCode::Submitted),
            'title' => Str::limit(fake()->sentence(4), 190),
            'description' => fake()->paragraph(4),
            'applicant_name' => fake()->name(),
            'applicant_phone' => fake()->numerify('+#?## ### ###'),
            'applicant_address' => fake()->streetAddress(),
            'priority' => Priority::Normal->value,
            'stage' => ApplicationStatusCode::Submitted->stage(),
            'submitted_at' => $submittedAt,
            'due_at' => (clone $submittedAt)->modify('+'.($service->sla_days ?: 15).' days'),
            'closed_at' => null,
            'last_activity_at' => $submittedAt,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'application_status_id' => $this->statusId(ApplicationStatusCode::Submitted),
            'stage' => ApplicationStatusCode::Submitted->stage(),
        ]);
    }

    public function underReview(): static
    {
        return $this->inState(ApplicationStatusCode::UnderReview);
    }

    public function documentsRequired(): static
    {
        return $this->inState(ApplicationStatusCode::DocumentsRequired);
    }

    public function inProgress(): static
    {
        return $this->inState(ApplicationStatusCode::InProgress);
    }

    public function pendingApproval(): static
    {
        return $this->inState(ApplicationStatusCode::PendingApproval);
    }

    public function completed(): static
    {
        return $this->closed(ApplicationStatusCode::Completed);
    }

    public function rejected(): static
    {
        return $this->closed(ApplicationStatusCode::Rejected);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'submitted_at' => now()->subDays(60),
            'due_at' => now()->subDays(5),
        ]);
    }

    public function forCitizen(User $citizen): static
    {
        return $this->state(fn (array $attributes) => ['citizen_id' => $citizen->id]);
    }

    public function assignedTo(?User $officer): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_officer_id' => $officer?->id,
            'department_id' => $officer?->department_id,
        ]);
    }

    private function inState(ApplicationStatusCode $code): static
    {
        return $this->state(fn (array $attributes) => [
            'application_status_id' => $this->statusId($code),
            'stage' => $code->stage(),
            'closed_at' => null,
        ]);
    }

    private function closed(ApplicationStatusCode $code): static
    {
        return $this->state(fn (array $attributes) => [
            'application_status_id' => $this->statusId($code),
            'stage' => $code->stage(),
            'closed_at' => now(),
        ]);
    }

    private function statusId(ApplicationStatusCode $code): int
    {
        return ApplicationStatus::firstOrCreate(
            ['code' => $code->value],
            [
                'name' => $code->label(),
                'sort_order' => $code->stage() * 10,
                'color' => 'gray',
                'is_terminal' => $code->isTerminal(),
                'is_citizen_visible' => true,
                'counts_as_open' => ! $code->isTerminal(),
            ]
        )->id;
    }
}
