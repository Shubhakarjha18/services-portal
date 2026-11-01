<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ApplicationStatusCode;
use App\Models\Application;
use App\Models\Department;
use Tests\PortalTestCase;

/**
 * CRUD surface of /api/v1/applications: row scoping per role, validation and
 * the audit trail each write is expected to leave behind.
 */
final class ApplicationTest extends PortalTestCase
{
    public function test_submission_is_rejected_without_a_token(): void
    {
        $this->postJson('/api/v1/applications', $this->applicationPayload())->assertUnauthorized();
    }

    public function test_citizen_can_submit_and_receives_a_tracking_reference(): void
    {
        $citizen = $this->citizen();

        $response = $this->submit($citizen)->assertCreated()
            ->assertJsonPath('data.status.code', ApplicationStatusCode::Submitted->value)
            ->assertJsonPath('data.stage', 1)
            ->assertJsonPath('data.progress', 0)
            ->assertJsonStructure(['data' => ['reference_number', 'timestamps' => ['submitted_at', 'due_at']]]);

        $reference = $response->json('data.reference_number');

        $this->assertMatchesRegularExpression('/^PSP-\d{4}-\d{6}$/', $reference);

        $application = Application::where('reference_number', $reference)->firstOrFail();

        $this->assertSame($citizen->id, $application->citizen_id);
        $this->assertSame($this->publicService()->department_id, $application->department_id);
        $this->assertNotNull($application->due_at);
        $this->assertNull($application->assigned_officer_id);

        // Submission is the first timeline entry and it is audited.
        $this->assertDatabaseHas('application_transitions', [
            'application_id' => $application->id,
            'note' => 'Application submitted by the applicant.',
            'visible_to_citizen' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'submitted',
            'reference_number' => $reference,
            'user_id' => $citizen->id,
        ]);
    }

    public function test_submission_validates_every_required_field(): void
    {
        $citizen = $this->citizen();

        $this->postJson('/api/v1/applications', [], [
            'Authorization' => 'Bearer '.$this->tokenFor($citizen),
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'service_type_id', 'title', 'description', 'applicant_name', 'applicant_phone', 'applicant_address',
        ]);
    }

    public function test_submission_messages_are_written_for_citizens_not_for_bots(): void
    {
        $citizen = $this->citizen();

        $response = $this->submit($citizen, [
            'title' => 'Short',
            'description' => 'too short',
            'applicant_phone' => 'call me',
            'applicant_address' => 'here',
            'service_type_id' => 99999,
        ])->assertUnprocessable();

        $response->assertJsonValidationErrors(['service_type_id', 'title', 'description', 'applicant_phone', 'applicant_address']);
        $this->assertSame(
            'The selected service is no longer available.',
            $response->json('errors.service_type_id.0')
        );
        $this->assertSame(
            'Use digits, spaces, brackets or a leading + only.',
            $response->json('errors.applicant_phone.0')
        );
    }

    public function test_a_citizen_cannot_raise_the_priority_of_their_own_case(): void
    {
        $citizen = $this->citizen();

        $this->submit($citizen, ['priority' => 'urgent'])->assertCreated();

        $this->assertSame('normal', Application::latest('id')->firstOrFail()->priority->value);
    }

    public function test_citizens_only_ever_see_their_own_submissions(): void
    {
        $mine = $this->citizen();
        $theirs = $this->citizen();

        $visible = Application::factory()->forCitizen($mine)->count(2)->create();
        Application::factory()->forCitizen($theirs)->count(3)->create();

        $ids = collect($this->apiAs($mine, 'get', '/api/v1/applications')->assertOk()->json('data'))->pluck('id');

        $this->assertCount(2, $ids);
        $this->assertEqualsCanonicalizing($visible->pluck('id')->all(), $ids->all());
    }

    public function test_an_officer_sees_the_departmental_queue_and_nothing_else(): void
    {
        $officer = $this->officer();
        $otherDepartment = Department::factory()->create();

        $inQueue = Application::factory()->create(['department_id' => $officer->department_id]);
        $outside = Application::factory()->create(['department_id' => $otherDepartment->id]);

        $ids = collect($this->apiAs($officer, 'get', '/api/v1/applications')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($inQueue->id));
        $this->assertFalse($ids->contains($outside->id));
    }

    public function test_an_admin_sees_every_case_and_can_filter_the_list(): void
    {
        $admin = $this->admin();
        $officer = $this->officer();

        $open = Application::factory()->underReview()->create([
            'assigned_officer_id' => $officer->id,
            'due_at' => now()->addDays(10),
        ]);
        $late = Application::factory()->underReview()->overdue()->create();
        $closed = Application::factory()->completed()->create();

        $this->assertCount(3, $this->apiAs($admin, 'get', '/api/v1/applications')->assertOk()->json('data'));

        $this->assertSame(
            [$late->reference_number],
            collect($this->apiAs($admin, 'get', '/api/v1/applications?overdue=1')->json('data'))->pluck('reference_number')->all()
        );

        $this->assertSame(
            [$open->reference_number],
            collect($this->apiAs($admin, 'get', '/api/v1/applications?assigned='.$officer->id)->json('data'))
                ->pluck('reference_number')->all()
        );

        $this->assertSame(
            ['completed'],
            collect($this->apiAs($admin, 'get', '/api/v1/applications?status='.ApplicationStatusCode::Completed->value)->json('data'))
                ->pluck('status.code')->unique()->values()->all()
        );

        $this->assertCount(
            1,
            $this->apiAs($admin, 'get', '/api/v1/applications?q='.$open->reference_number)->json('data')
        );
    }

    public function test_an_unknown_status_filter_is_ignored_instead_of_erroring(): void
    {
        $admin = $this->admin();
        Application::factory()->count(2)->create();

        $this->assertCount(2, $this->apiAs($admin, 'get', '/api/v1/applications?status=not_a_status')->json('data'));
    }

    public function test_show_returns_the_full_case_with_legal_transitions(): void
    {
        $citizen = $this->citizen();
        $application = Application::factory()->forCitizen($citizen)->submitted()->create();

        $response = $this->apiAs($citizen, 'get', "/api/v1/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.reference_number', $application->reference_number)
            ->assertJsonStructure(['data', 'links' => ['self', 'timeline'], 'meta' => ['documents', 'comments', 'timeline']]);

        // A citizen at "submitted" may only withdraw, so the UI shows one button.
        $this->assertSame(
            [ApplicationStatusCode::Cancelled->value],
            collect($response->json('data.next_statuses'))->pluck('code')->all()
        );

        $this->assertSame(1, $application->fresh()->view_count);
    }

    public function test_another_citizen_cannot_open_the_case(): void
    {
        $intruder = $this->citizen();
        $application = Application::factory()->create();

        $this->apiAs($intruder, 'get', "/api/v1/applications/{$application->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'http_error');

        $this->apiAs($intruder, 'get', '/api/v1/applications/999999')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_internal_notes_never_reach_the_applicant(): void
    {
        $citizen = $this->citizen();
        $officer = $this->officer();
        $application = Application::factory()->forCitizen($citizen)
            ->underReview()
            ->create(['department_id' => $officer->department_id, 'assigned_officer_id' => $officer->id]);

        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/comments", [
            'body' => 'Contact the registry by phone, do not tell the applicant.',
            'is_internal' => true,
        ])->assertCreated()->assertJsonPath('data.is_internal', true);

        $this->assertCount(0, $this->apiAs($citizen, 'get', "/api/v1/applications/{$application->id}")->json('meta.comments'));
        $this->assertCount(1, $this->apiAs($officer, 'get', "/api/v1/applications/{$application->id}")->json('meta.comments'));
    }

    public function test_a_citizen_cannot_flag_a_comment_as_internal(): void
    {
        $citizen = $this->citizen();
        $application = Application::factory()->forCitizen($citizen)->create();

        $this->apiAs($citizen, 'post', "/api/v1/applications/{$application->id}/comments", [
            'body' => 'Just checking in.',
            'is_internal' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors(['is_internal']);

        $this->apiAs($citizen, 'post', "/api/v1/applications/{$application->id}/comments", ['body' => 'hi'])
            ->assertUnprocessable()->assertJsonPath('errors.body.0', 'Your message is too short.');
    }

    public function test_a_stranger_cannot_comment_on_a_case(): void
    {
        $application = Application::factory()->create();

        $this->apiAs($this->citizen(), 'post', "/api/v1/applications/{$application->id}/comments", ['body' => 'Not my file'])
            ->assertForbidden();
    }

    public function test_a_citizen_can_edit_only_an_untouched_submission(): void
    {
        $citizen = $this->citizen();
        $application = Application::factory()->forCitizen($citizen)->submitted()->create();

        $this->apiAs($citizen, 'patch', "/api/v1/applications/{$application->id}", [
            'title' => 'Renewal of national identity card (corrected)',
        ])->assertOk()->assertJsonPath('data.title', 'Renewal of national identity card (corrected)');

        $this->apiAs($citizen, 'patch', "/api/v1/applications/{$application->id}", ['priority' => 'urgent'])
            ->assertUnprocessable()->assertJsonPath('errors.priority.0', 'Only portal staff can change the priority of an application.');

        // Once an officer picks the case up the applicant loses edit rights.
        $reviewed = Application::factory()->forCitizen($citizen)->underReview()->create();

        $this->apiAs($citizen, 'patch', "/api/v1/applications/{$reviewed->id}", ['title' => 'Trying again'])
            ->assertForbidden();
    }

    public function test_an_officer_can_correct_a_case_and_it_is_audited(): void
    {
        $officer = $this->officer();
        $application = Application::factory()->underReview()
            ->create(['department_id' => $officer->department_id, 'assigned_officer_id' => $officer->id]);

        $this->apiAs($officer, 'patch', "/api/v1/applications/{$application->id}", ['priority' => 'high'])
            ->assertOk()->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'application_updated',
            'reference_number' => $application->reference_number,
            'user_id' => $officer->id,
        ]);
    }

    public function test_withdrawal_soft_deletes_the_case_and_keeps_the_trail(): void
    {
        $citizen = $this->citizen();
        $application = Application::factory()->forCitizen($citizen)->submitted()->create();

        $this->apiAs($citizen, 'delete', "/api/v1/applications/{$application->id}")
            ->assertOk()
            ->assertJsonPath('message', "Application {$application->reference_number} was withdrawn.");

        $this->assertSoftDeleted('applications', ['id' => $application->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'application_withdrawn', 'user_id' => $citizen->id]);

        // An officer cannot delete a case, only move it.
        $other = Application::factory()->create();
        $this->apiAs($this->officer(), 'delete', "/api/v1/applications/{$other->id}")->assertForbidden();
    }

    public function test_the_timeline_endpoint_supports_incremental_polling(): void
    {
        $citizen = $this->citizen();
        $officer = $this->officer();
        $application = Application::factory()->forCitizen($citizen)
            ->create(['department_id' => $officer->department_id]);

        $first = $this->apiAs($citizen, 'get', "/api/v1/applications/{$application->id}/timeline")
            ->assertOk()
            ->assertJsonPath('meta.status', ApplicationStatusCode::Submitted->value);

        $lastId = (int) collect($first->json('data'))->max('id');

        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::UnderReview->value,
            'note' => 'Assigned to the registry desk.',
        ])->assertOk();

        $delta = $this->apiAs($citizen, 'get', "/api/v1/applications/{$application->id}/timeline?since_id={$lastId}")
            ->assertOk();

        $this->assertCount(1, $delta->json('data'));
        $this->assertSame(ApplicationStatusCode::UnderReview->value, $delta->json('data.0.status'));
        $this->assertSame(2, $delta->json('meta.stage'));
        $this->assertSame(17, $delta->json('meta.progress'));
        $this->assertArrayHasKey('poll_after_seconds', $delta->json('meta'));
    }

    public function test_only_staff_can_assign_a_case(): void
    {
        $admin = $this->admin();
        $officer = $this->officer();
        $application = Application::factory()->create(['department_id' => $officer->department_id]);

        $this->apiAs($this->citizen(), 'post', "/api/v1/applications/{$application->id}/assign", ['officer_id' => $officer->id])
            ->assertForbidden();

        $this->apiAs($admin, 'post', "/api/v1/applications/{$application->id}/assign", ['officer_id' => $officer->id])
            ->assertOk()
            ->assertJsonPath('data.officer.id', $officer->id);

        $this->assertDatabaseHas('audit_logs', ['event' => 'assigned', 'user_id' => $admin->id]);

        // Assignment targets must actually be active officers.
        $this->apiAs($admin, 'post', "/api/v1/applications/{$application->id}/assign", ['officer_id' => $this->citizen()->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.officer_id.0', 'The selected officer is inactive or does not hold the Case Officer role.');
    }

    public function test_an_inactive_account_is_locked_out_of_the_api(): void
    {
        $citizen = $this->citizen();
        $token = $this->tokenFor($citizen);
        $application = Application::factory()->forCitizen($citizen)->create();

        $this->apiAs($citizen, 'get', "/api/v1/applications/{$application->id}")->assertOk();

        $citizen->forceFill(['is_active' => false])->save();

        $this->getJson("/api/v1/applications/{$application->id}", ['Authorization' => 'Bearer '.$token])
            ->assertForbidden();
    }

    public function test_the_list_is_paginated_and_the_page_size_is_clamped(): void
    {
        $admin = $this->admin();
        Application::factory()->count(6)->create();

        $page = $this->apiAs($admin, 'get', '/api/v1/applications?per_page=4')->assertOk();

        $this->assertCount(4, $page->json('data'));
        $this->assertSame(6, $page->json('meta.total'));

        // per_page is clamped: a client cannot ask for the whole table at once.
        $this->assertLessThanOrEqual(
            100,
            count($this->apiAs($admin, 'get', '/api/v1/applications?per_page=5000')->assertOk()->json('data'))
        );
    }
}
