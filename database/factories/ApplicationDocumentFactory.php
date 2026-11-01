<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    protected $model = ApplicationDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::random(6).'.pdf';

        return [
            'application_id' => Application::factory(),
            'uploaded_by' => UserFactory::new(),
            'label' => Str::title(fake()->words(2, true)),
            'original_name' => $name,
            'path' => 'applications/TEST-000000/'.$name,
            'disk' => (string) config('portal.documents.disk', 'documents'),
            'mime_type' => 'application/pdf',
            'size_bytes' => fake()->numberBetween(20000, 4000000),
            'checksum' => hash('sha256', $name),
            'status' => DocumentStatus::Pending->value,
            'review_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Accepted->value,
            'review_note' => 'Copy matches the registry entry.',
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Rejected->value,
            'review_note' => 'The scan is unreadable, please upload a clearer copy.',
            'reviewed_at' => now(),
        ]);
    }
}
