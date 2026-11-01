<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatusCode;
use App\Enums\DocumentStatus;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatus;
use App\Models\ServiceType;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplicationService
{
    public function __construct(
        private readonly ReferenceNumberGenerator $references,
        private readonly ApplicationWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $citizen, array $data): Application
    {
        $service = ServiceType::with('department')->findOrFail((int) $data['service_type_id']);

        return DB::transaction(function () use ($citizen, $data, $service): Application {
            $submittedAt = now();

            $application = Application::create([
                'reference_number' => $this->references->generate(),
                'service_type_id' => $service->id,
                'department_id' => $service->department_id,
                'citizen_id' => $citizen->id,
                'application_status_id' => ApplicationStatus::where('code', ApplicationStatusCode::Submitted->value)->value('id'),
                'title' => $data['title'],
                'description' => $data['description'],
                'applicant_name' => $data['applicant_name'],
                'applicant_phone' => $data['applicant_phone'],
                'applicant_address' => $data['applicant_address'],
                'priority' => $data['priority'] ?? 'normal',
                'stage' => ApplicationStatusCode::Submitted->stage(),
                'submitted_at' => $submittedAt,
                'due_at' => $submittedAt->copy()->addDays((int) ($service->sla_days ?: config('portal.default_sla_days'))),
                'last_activity_at' => $submittedAt,
            ]);

            $this->workflow->recordSubmission($application, $citizen);

            return $application->load(['status', 'serviceType.department', 'department', 'citizen']);
        });
    }

    public function attachDocument(Application $application, User $uploader, UploadedFile $file, string $label, ?string $note = null): ApplicationDocument
    {
        $disk = (string) config('portal.documents.disk', 'documents');
        $directory = trim((string) config('portal.documents.storage_prefix'), '/').'/'.$application->reference_number;

        $path = $file->store($directory, $disk);
        $stored = $application->documents()->create([
            'application_id' => $application->id,
            'uploaded_by' => $uploader->id,
            'label' => $label,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize() ?: 0,
            'checksum' => (string) hash_file('sha256', $file->getRealPath()),
            'status' => DocumentStatus::Pending->value,
        ]);

        $application->forceFill(['last_activity_at' => now()])->save();

        $this->audit->log(
            event: 'document_uploaded',
            subject: $stored,
            new: [
                'label' => $label,
                'file' => $file->getClientOriginalName(),
                'checksum' => $stored->checksum,
                'size_bytes' => $stored->size_bytes,
            ],
            meta: ['note' => $note, 'application_reference' => $application->reference_number],
            actor: $uploader,
        );

        return $stored;
    }

    public function removeDocument(ApplicationDocument $document, User $actor): void
    {
        $snapshot = $document->only(['label', 'original_name', 'checksum', 'application_id']);

        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        $this->audit->log(
            event: 'document_deleted',
            subject: $document,
            old: $snapshot,
            actor: $actor,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reviewDocument(ApplicationDocument $document, array $data, User $reviewer): ApplicationDocument
    {
        $before = ['status' => $document->status->value, 'review_note' => $document->review_note];

        $document->forceFill([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();

        $this->audit->log(
            event: 'document_reviewed',
            subject: $document,
            old: $before,
            new: ['status' => $document->status->value, 'review_note' => $document->review_note],
            meta: ['application_reference' => $document->application?->reference_number],
            actor: $reviewer,
        );

        return $document->load('reviewer');
    }
}
