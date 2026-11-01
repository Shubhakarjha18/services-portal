<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ApplicationStatusCode;
use App\Enums\Priority;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\ApplicationWorkflow;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Demo corpus. Cases are driven through the real workflow service, so each
 * seeded application carries a genuine timeline and audit trail.
 */
class DemoApplicationSeeder extends Seeder
{
    /** @var array<int, array{name: string, path: array<int, ApplicationStatusCode>, docs: int}> */
    private const SCENARIOS = [
        ['name' => 'Just submitted', 'path' => [], 'docs' => 0],
        ['name' => 'In review', 'path' => [ApplicationStatusCode::UnderReview], 'docs' => 1],
        ['name' => 'Waiting for paperwork', 'path' => [ApplicationStatusCode::UnderReview, ApplicationStatusCode::DocumentsRequired], 'docs' => 1],
        ['name' => 'Paperwork fixed', 'path' => [
            ApplicationStatusCode::UnderReview,
            ApplicationStatusCode::DocumentsRequired,
            ApplicationStatusCode::UnderReview,
            ApplicationStatusCode::InProgress,
        ], 'docs' => 2],
        ['name' => 'Printing stage', 'path' => [
            ApplicationStatusCode::UnderReview,
            ApplicationStatusCode::InProgress,
            ApplicationStatusCode::PendingApproval,
        ], 'docs' => 2],
        ['name' => 'Fully completed', 'path' => [
            ApplicationStatusCode::UnderReview,
            ApplicationStatusCode::InProgress,
            ApplicationStatusCode::PendingApproval,
            ApplicationStatusCode::Approved,
            ApplicationStatusCode::Completed,
        ], 'docs' => 2],
        ['name' => 'Refused at review', 'path' => [
            ApplicationStatusCode::UnderReview,
            ApplicationStatusCode::Rejected,
        ], 'docs' => 1],
        ['name' => 'Held for registry reply', 'path' => [
            ApplicationStatusCode::UnderReview,
            ApplicationStatusCode::OnHold,
        ], 'docs' => 1],
        ['name' => 'Back on track', 'path' => [
            ApplicationStatusCode::UnderReview,
            ApplicationStatusCode::OnHold,
            ApplicationStatusCode::InProgress,
            ApplicationStatusCode::PendingApproval,
        ], 'docs' => 2],
        ['name' => 'Withdrawn by applicant', 'path' => [
            ApplicationStatusCode::UnderReview,
            ApplicationStatusCode::Cancelled,
        ], 'docs' => 1],
    ];

    public function run(): void
    {
        $service = app(ApplicationService::class);
        $workflow = app(ApplicationWorkflow::class);

        $citizens = User::roleNamed(RoleName::Public)->get();
        $officers = User::roleNamed(RoleName::Officer)->get();
        $admin = User::roleNamed(RoleName::Admin)->first();
        $services = ServiceType::with('department')->get();

        if ($citizens->isEmpty() || $officers->isEmpty() || $admin === null || $services->isEmpty()) {
            $this->command?->warn('DemoApplicationSeeder skipped: seed roles, users and the catalog first.');

            return;
        }

        $created = 0;

        foreach ($this->plans($services) as $plan) {
            $citizen = $citizens[$plan['citizen_index'] % $citizens->count()];
            $officer = $officers[$plan['citizen_index'] % $officers->count()];

            $application = $service->create($citizen, [
                'service_type_id' => $plan['service']->id,
                'title' => $plan['title'],
                'description' => $plan['description'],
                'applicant_name' => $citizen->name,
                'applicant_phone' => $citizen->phone ?? '+1 555 010 0100',
                'applicant_address' => fake()->streetAddress(),
                'priority' => $plan['priority'],
            ]);

            $this->attachDocuments($application, $citizen, $officer, $plan['docs']);

            $application->forceFill(['assigned_officer_id' => $officer->id])->save();

            foreach ($plan['path'] as $step) {
                $actor = $this->actorFor($step, $citizen, $officer, $admin);

                $workflow->transition($application, $step->value, $this->noteFor($step), $actor);
            }

            $this->backdate($application, $plan['service']);

            $created++;
        }

        $this->command?->info("Created {$created} demo applications with timelines and audit entries.");
    }

    /**
     * @param  Collection<int, ServiceType>  $services
     * @return array<int, array<string, mixed>>
     */
    private function plans($services): array
    {
        $titles = [
            'Replacement birth certificate',
            'Renew my national ID card',
            'Building permit for a two-storey house',
            'Tax clearance for a company tender',
            'Upgrade my driver licence',
            'Medical report for an insurance claim',
            'Zoning confirmation for plot 42',
            'New business tax registration',
            'Vehicle registration transfer',
            'Clinic operating licence renewal',
        ];

        $plans = [];
        $total = $services->count();

        foreach (self::SCENARIOS as $index => $scenario) {
            foreach ([0, 1] as $repeat) {
                $serviceIndex = ($index * 2 + $repeat) % $total;
                $service = $services[$serviceIndex];

                $plans[] = [
                    'service' => $service,
                    'citizen_index' => $index + $repeat,
                    'title' => $titles[($index + $repeat) % count($titles)],
                    'description' => $scenario['name'].' — requested via the public portal. '
                        .'Service: '.$service->name.'. Expected turnaround: '.$service->sla_days.' working days.',
                    'priority' => ($index + $repeat) % 5 === 0 ? Priority::High->value : Priority::Normal->value,
                    'path' => $scenario['path'],
                    'docs' => $scenario['docs'],
                ];
            }
        }

        return $plans;
    }

    private function actorFor(ApplicationStatusCode $step, User $citizen, User $officer, User $admin): User
    {
        return match ($step) {
            ApplicationStatusCode::Cancelled => $citizen,
            ApplicationStatusCode::Approved, ApplicationStatusCode::Completed => $admin,
            ApplicationStatusCode::Rejected => $admin,
            default => $officer,
        };
    }

    private function noteFor(ApplicationStatusCode $code): string
    {
        return match ($code) {
            ApplicationStatusCode::UnderReview => 'Case picked from the departmental queue and opened for review.',
            ApplicationStatusCode::DocumentsRequired => 'A submitted document does not meet the requirement; the applicant was notified.',
            ApplicationStatusCode::InProgress => 'Checks passed, the department is producing the document.',
            ApplicationStatusCode::OnHold => 'Paused while an external registry confirms the record.',
            ApplicationStatusCode::PendingApproval => 'Work is finished and awaits the administrator sign-off.',
            ApplicationStatusCode::Approved => 'Approved by the administrator.',
            ApplicationStatusCode::Completed => 'Closed: the document was collected and handed over.',
            ApplicationStatusCode::Rejected => 'Refused: the eligibility rules for this service are not met.',
            ApplicationStatusCode::Cancelled => 'Withdrawn at the request of the applicant.',
            default => 'Status updated through the portal.',
        };
    }

    private function attachDocuments(Application $application, User $citizen, User $officer, int $count): void
    {
        if ($count === 0) {
            return;
        }

        $labels = $application->serviceType->requiredDocumentLabels() ?: ['Supporting document'];
        $audit = app(AuditLogger::class);

        foreach (array_slice($labels, 0, $count) as $index => $label) {
            $file = Str::slug($label).'-'.($index + 1).'.pdf';

            $document = $application->documents()->create([
                'application_id' => $application->id,
                'uploaded_by' => $citizen->id,
                'label' => $label,
                'original_name' => $file,
                'path' => config('portal.documents.storage_prefix').'/'.$application->reference_number.'/'.$file,
                'disk' => (string) config('portal.documents.disk', 'documents'),
                'mime_type' => 'application/pdf',
                'size_bytes' => random_int(50000, 900000),
                'checksum' => hash('sha256', $application->reference_number.$label),
                'status' => $count > 1 && $index === $count - 1 ? 'pending' : 'accepted',
                'review_note' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            $audit->log(
                event: 'document_uploaded',
                subject: $document,
                new: ['label' => $label, 'file' => $document->original_name, 'checksum' => $document->checksum],
                meta: ['seeded' => true],
                actor: $citizen,
            );
        }
    }

    /** Spread submissions over the past month so charts and SLA widgets have data. */
    private function backdate(Application $application, ServiceType $service): void
    {
        $submittedAt = now()->subDays(random_int(1, 28))->subHours(random_int(0, 23))->startOfMinute();

        $application->forceFill([
            'submitted_at' => $submittedAt,
            'due_at' => $submittedAt->copy()->addDays((int) ($service->sla_days ?: config('portal.default_sla_days'))),
            'created_at' => $submittedAt,
            'last_activity_at' => $submittedAt->copy()->addDays(min(3, (int) $submittedAt->diffInDays(now()))),
        ])->save();

        $first = $application->transitions()->orderBy('id')->value('id');

        $application->transitions()
            ->where('id', $first)
            ->update(['created_at' => $submittedAt, 'updated_at' => $submittedAt]);
    }
}
