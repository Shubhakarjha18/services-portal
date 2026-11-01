<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ApplicationStatusCode;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\AuditLog;
use Tests\PortalTestCase;

/**
 * The workflow engine: every move must be declared in config/portal.php, must
 * be allowed for the acting role and must leave both a timeline row and a
 * compliance entry behind.
 */
final class WorkflowTest extends PortalTestCase
{
    public function test_an_officer_moves_a_case_and_both_records_are_written(): void
    {
        $officer = $this->officer();
        $application = Application::factory()->submitted()
            ->create(['department_id' => $officer->department_id]);

        $response = $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::UnderReview->value,
            'note' => 'Started checking the registry extract.',
        ])->assertOk()->assertJsonPath('meta.transition.status', ApplicationStatusCode::UnderReview->value);

        $this->assertSame(ApplicationStatusCode::UnderReview->value, $response->json('data.status.code'));
        $this->assertSame(2, $response->json('data.stage'));

        $application->refresh();

        $this->assertSame(ApplicationStatusCode::UnderReview, $application->code());
        $this->assertSame(2, $application->stage);
        $this->assertNull($application->closed_at);

        // An officer touching an unassigned queue item claims it.
        $this->assertSame($officer->id, $application->assigned_officer_id);

        $this->assertDatabaseHas('application_transitions', [
            'application_id' => $application->id,
            'performed_by' => $officer->id,
            'note' => 'Started checking the registry extract.',
            'stage' => 2,
        ]);

        $audit = AuditLog::where('event', 'status_changed')->firstOrFail();

        $this->assertSame($officer->id, $audit->user_id);
        $this->assertSame($application->reference_number, $audit->reference_number);
        $this->assertSame('submitted', $audit->old_values['status']);
        $this->assertSame('under_review', $audit->new_values['status']);
        $this->assertSame(RoleName::Officer->value, $audit->role_name);
    }

    public function test_an_undeclared_transition_is_refused_with_a_machine_readable_code(): void
    {
        $officer = $this->officer();
        $application = Application::factory()->submitted()
            ->create(['department_id' => $officer->department_id, 'assigned_officer_id' => $officer->id]);

        // submitted -> approved is not an edge in the workflow at all.
        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::Approved->value,
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'workflow_violation');

        $this->assertSame('submitted', $application->fresh()->code()->value);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'status_changed']);
    }

    public function test_an_unknown_status_is_a_validation_error_not_a_workflow_error(): void
    {
        $admin = $this->admin();
        $application = Application::factory()->submitted()->create();

        $this->apiAs($admin, 'post', "/api/v1/applications/{$application->id}/status", ['status' => 'paid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath('errors.status.0', 'That status is not part of the portal workflow.');
    }

    public function test_moving_a_case_to_the_status_it_already_has_is_refused(): void
    {
        $admin = $this->admin();
        $application = Application::factory()->submitted()->create();

        $this->apiAs($admin, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::Submitted->value,
        ])->assertUnprocessable()->assertJsonPath('message', 'The application is already Submitted.');
    }

    public function test_a_transition_has_to_explain_itself(): void
    {
        $officer = $this->officer();
        $application = Application::factory()->underReview()
            ->create(['department_id' => $officer->department_id, 'assigned_officer_id' => $officer->id]);

        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::OnHold->value,
            'note' => 'why',
        ])->assertUnprocessable()->assertJsonPath('errors.note.0', 'Add a short explanation (at least 5 characters) so the applicant and the audit trail both record why.');
    }

    public function test_only_an_admin_can_approve_or_reject_from_pending_approval(): void
    {
        $officer = $this->officer();
        $admin = $this->admin();
        $application = Application::factory()->pendingApproval()
            ->create(['department_id' => $officer->department_id, 'assigned_officer_id' => $officer->id]);

        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::Approved->value,
        ])->assertUnprocessable()->assertJsonPath('code', 'workflow_violation');

        // The officer can still send it back to processing.
        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::InProgress->value,
        ])->assertOk();

        $this->apiAs($admin, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::PendingApproval->value,
        ])->assertOk();

        $this->apiAs($admin, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::Approved->value,
            'note' => 'Signed off by the director.',
        ])->assertOk()->assertJsonPath('data.stage', 6);

        $this->assertNull($application->fresh()->closed_at, 'Approved is not a terminal state.');
    }

    public function test_a_terminal_case_cannot_move_anymore(): void
    {
        $admin = $this->admin();
        $application = Application::factory()->completed()->create();

        $this->apiAs($admin, 'get', "/api/v1/applications/{$application->id}/status-options")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->apiAs($admin, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::InProgress->value,
        ])->assertUnprocessable()->assertJsonPath('code', 'workflow_violation');

        $this->assertSame(100, $application->fresh()->progressPercentage());
    }

    public function test_the_applicant_can_withdraw_but_cannot_drive_the_case(): void
    {
        $citizen = $this->citizen();
        $application = Application::factory()->forCitizen($citizen)->submitted()->create();

        $this->apiAs($citizen, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::UnderReview->value,
        ])->assertUnprocessable()->assertJsonPath('code', 'workflow_violation');

        $this->apiAs($citizen, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::Cancelled->value,
            'note' => 'I no longer need the certificate.',
        ])->assertOk()->assertJsonPath('data.status.code', ApplicationStatusCode::Cancelled->value);

        $closed = $application->fresh();

        $this->assertNotNull($closed->closed_at);
        $this->assertTrue($closed->code()->isTerminal());
        $this->assertDatabaseHas('application_transitions', [
            'application_id' => $closed->id,
            'visible_to_citizen' => true,
        ]);
    }

    public function test_a_citizen_cannot_touch_someone_elses_case(): void
    {
        $intruder = $this->citizen();
        $application = Application::factory()->submitted()->create();

        $this->apiAs($intruder, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::Cancelled->value,
        ])->assertForbidden();
    }

    public function test_the_status_options_endpoints_only_offer_legal_moves(): void
    {
        $officer = $this->officer();
        $application = Application::factory()->underReview()
            ->create(['department_id' => $officer->department_id, 'assigned_officer_id' => $officer->id]);

        $codes = collect($this->apiAs($officer, 'get', "/api/v1/applications/{$application->id}/status-options")
            ->assertOk()
            ->json('data'))->pluck('code');

        $this->assertEqualsCanonicalizing(
            [
                ApplicationStatusCode::DocumentsRequired->value,
                ApplicationStatusCode::InProgress->value,
                ApplicationStatusCode::OnHold->value,
                ApplicationStatusCode::Rejected->value,
                // rejected needs applications.decide, which officers do have.
            ],
            $codes->all()
        );

        $this->assertSame('under_review', $this->apiAs($officer, 'get', "/api/v1/applications/{$application->id}/status-options")->json('meta.current'));
    }

    public function test_a_move_can_be_kept_internal(): void
    {
        $citizen = $this->citizen();
        $officer = $this->officer();
        $application = Application::findOrFail($this->submit($citizen)->assertCreated()->json('data.id'));

        $this->assertSame($officer->department_id, $application->department_id);

        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::UnderReview->value,
            'note' => 'Waiting for an internal registry check.',
            'internal_only' => true,
        ])->assertOk();

        $this->assertCount(1, $this->apiAs($citizen, 'get', "/api/v1/applications/{$application->id}/timeline")->json('data'));
        $this->assertCount(2, $this->apiAs($officer, 'get', "/api/v1/applications/{$application->id}/timeline")->json('data'));

        $this->assertDatabaseHas('application_transitions', [
            'application_id' => $application->id,
            'visible_to_citizen' => false,
        ]);
    }

    public function test_an_officer_cannot_work_a_case_from_another_department(): void
    {
        $officer = $this->officer();
        $application = Application::factory()->submitted()->create();

        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::UnderReview->value,
        ])->assertForbidden();
    }

    public function test_rejected_documents_can_be_resubmitted_and_re_reviewed(): void
    {
        $citizen = $this->citizen();
        $officer = $this->officer();
        $application = Application::factory()->forCitizen($citizen)->documentsRequired()
            ->create(['department_id' => $officer->department_id, 'assigned_officer_id' => $officer->id]);

        $document = ApplicationDocument::factory()->create([
            'application_id' => $application->id,
            'uploaded_by' => $citizen->id,
        ]);

        $this->apiAs($officer, 'patch', "/api/v1/documents/{$document->id}/review", [
            'status' => 'rejected',
            'review_note' => 'The scan is unreadable at the edges.',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::UnderReview->value,
        ])->assertOk()->assertJsonPath('data.stage', 2);
    }
}
