<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\PortalTestCase;

/**
 * Paperwork: private storage, checksummed uploads and a review queue that only
 * the officer holding the case (or an admin) can work through.
 */
final class DocumentTest extends PortalTestCase
{
    private Application $application;

    private User $citizen;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');

        $this->citizen = $this->citizen();
        $this->officer = $this->officer();

        $this->application = Application::factory()->forCitizen($this->citizen)->documentsRequired()
            ->create([
                'department_id' => $this->officer->department_id,
                'assigned_officer_id' => $this->officer->id,
            ]);
    }

    public function test_a_citizen_upload_lands_on_the_private_disk_with_a_checksum(): void
    {
        $response = $this->upload($this->citizen, UploadedFile::fake()->createWithContent('lease.pdf', '%PDF-1.4 lease agreement'))
            ->assertCreated()
            ->assertJsonPath('data.label', 'Proof of address')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.original_name', 'lease.pdf');

        $document = ApplicationDocument::findOrFail($response->json('data.id'));

        $this->assertSame(64, strlen($document->checksum));
        $this->assertSame(
            hash('sha256', '%PDF-1.4 lease agreement'),
            $document->checksum,
            'The stored checksum has to match the bytes the applicant sent.'
        );
        $this->assertStringStartsWith('applications/'.$this->application->reference_number.'/', $document->path);
        Storage::disk('documents')->assertExists($document->path);

        // The API download route is the only way in, which is what makes the
        // audit entry meaningful.
        $this->assertStringContainsString("/api/v1/documents/{$document->id}/download", $response->json('data.download_url'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document_uploaded',
            'user_id' => $this->citizen->id,
            'reference_number' => $this->application->reference_number,
        ]);
    }

    public function test_the_upload_endpoint_refuses_a_wrong_format(): void
    {
        $this->upload($this->citizen, UploadedFile::fake()->create('payload.exe', 12))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'])
            ->assertJsonPath('errors.file.0', 'Allowed formats: PDF, JPG, JPEG, PNG, WEBP, DOC, DOCX, XLS, XLSX.');
    }

    public function test_a_document_without_a_label_is_rejected(): void
    {
        $this->post("/api/v1/applications/{$this->application->id}/documents", [
            'file' => UploadedFile::fake()->create('lease.pdf', 8),
        ], ['Authorization' => 'Bearer '.$this->tokenFor($this->citizen)])
            ->assertUnprocessable()
            ->assertJsonPath('errors.label.0', 'Select which document you are uploading.');
    }

    public function test_oversized_files_are_refused_before_they_hit_the_disk(): void
    {
        $this->upload($this->citizen, UploadedFile::fake()->create('huge.pdf', 20_000))
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'The document must be smaller than 10 MB.');

        $this->assertSame(0, ApplicationDocument::count());
    }

    public function test_a_closed_case_no_longer_accepts_paperwork(): void
    {
        $closed = Application::factory()->forCitizen($this->citizen)->completed()->create();

        $this->upload($this->citizen, UploadedFile::fake()->create('late.pdf', 4), $closed)
            ->assertForbidden();
    }

    public function test_the_officer_holding_the_case_verifies_a_document(): void
    {
        $document = $this->document();

        $this->apiAs($this->officer, 'patch', "/api/v1/documents/{$document->id}/review", [
            'status' => 'accepted',
            'review_note' => 'Copy matches the registry entry.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.review_note', 'Copy matches the registry entry.');

        $document->refresh();

        $this->assertSame($this->officer->id, $document->reviewed_by);
        $this->assertNotNull($document->reviewed_at);
        $this->assertTrue($document->isVerified());

        $audit = AuditLog::where('event', 'document_reviewed')->firstOrFail();
        $this->assertSame($this->officer->id, $audit->user_id);
        $this->assertSame('pending', $audit->old_values['status']);
        $this->assertSame('accepted', $audit->new_values['status']);
    }

    public function test_the_applicant_is_not_shown_the_internal_review_note(): void
    {
        $document = $this->document();

        $this->apiAs($this->officer, 'patch', "/api/v1/documents/{$document->id}/review", [
            'status' => 'rejected',
            'review_note' => 'Signature page missing, ask them to re-scan.',
        ])->assertOk();

        $this->assertStringNotContainsString(
            'ask them to re-scan',
            $this->apiAs($this->citizen, 'get', "/api/v1/applications/{$this->application->id}")->getContent()
        );
    }

    public function test_a_rejection_has_to_be_explained(): void
    {
        $document = $this->document();

        $this->apiAs($this->officer, 'patch', "/api/v1/documents/{$document->id}/review", ['status' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.review_note.0', 'Explain why the document was rejected so the applicant knows what to re-upload.');

        $this->apiAs($this->officer, 'patch', "/api/v1/documents/{$document->id}/review", ['status' => 'archived'])
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_only_staff_with_the_case_can_review_it(): void
    {
        $document = $this->document();
        $anotherDepartment = User::factory()->officer()->create();

        $this->apiAs($this->citizen, 'patch', "/api/v1/documents/{$document->id}/review", [
            'status' => 'accepted',
        ])->assertForbidden();

        $this->apiAs($anotherDepartment, 'patch', "/api/v1/documents/{$document->id}/review", [
            'status' => 'accepted',
        ])->assertForbidden();

        // An admin can, because approvals have to be auditable centrally.
        $this->apiAs($this->admin(), 'patch', "/api/v1/documents/{$document->id}/review", [
            'status' => 'accepted',
        ])->assertOk();
    }

    public function test_downloading_a_document_is_authorized_and_audited(): void
    {
        $document = $this->document();

        $this->getJson("/api/v1/documents/{$document->id}/download", [
            'Authorization' => 'Bearer '.$this->tokenFor($this->citizen),
        ])->assertOk()->assertDownload($document->original_name);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document_downloaded',
            'user_id' => $this->citizen->id,
        ]);

        $this->getJson("/api/v1/documents/{$document->id}/download", [
            'Authorization' => 'Bearer '.$this->tokenFor($this->citizen()),
        ])->assertForbidden();
    }

    public function test_a_missing_file_produces_a_404_instead_of_a_broken_download(): void
    {
        $document = $this->document();
        Storage::disk('documents')->delete($document->path);

        $this->apiAs($this->citizen, 'get', "/api/v1/documents/{$document->id}/download")
            ->assertNotFound();
    }

    public function test_an_applicant_can_pull_back_an_unverified_upload(): void
    {
        $document = $this->document();

        $this->apiAs($this->citizen, 'delete', "/api/v1/documents/{$document->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Document removed.');

        $this->assertSoftDeleted('application_documents', ['id' => $document->id]);
        Storage::disk('documents')->assertMissing($document->path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'document_deleted', 'user_id' => $this->citizen->id]);
    }

    public function test_an_already_verified_document_stays_with_the_department(): void
    {
        $document = $this->document();

        $this->apiAs($this->officer, 'patch', "/api/v1/documents/{$document->id}/review", ['status' => 'accepted'])->assertOk();

        $this->apiAs($this->citizen, 'delete', "/api/v1/documents/{$document->id}")->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function upload(User $user, UploadedFile $file, ?Application $application = null, array $extra = []): TestResponse
    {
        return $this->post(
            '/api/v1/applications/'.($application ?? $this->application)->id.'/documents',
            ['label' => 'Proof of address', 'file' => $file, ...$extra],
            ['Authorization' => 'Bearer '.$this->tokenFor($user)],
        );
    }

    /** A document the applicant has already uploaded for this case. */
    private function document(): ApplicationDocument
    {
        $response = $this->upload($this->citizen, UploadedFile::fake()->create('lease.pdf', 6));

        return ApplicationDocument::findOrFail($response->json('data.id'));
    }
}
