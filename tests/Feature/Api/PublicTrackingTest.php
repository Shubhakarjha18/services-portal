<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ApplicationStatusCode;
use App\Models\Application;
use App\Models\AuditLog;
use Illuminate\Testing\TestResponse;
use Tests\PortalTestCase;

/**
 * The anonymous tracker. A tracking number is not a secret, so this endpoint
 * answers the "where is my document?" question while keeping contact details
 * masked until the caller proves ownership with their citizen number.
 */
final class PublicTrackingTest extends PortalTestCase
{
    private function track(array $query = [], array $headers = []): TestResponse
    {
        return $this->getJson('/api/v1/track?'.http_build_query($query), $headers);
    }

    public function test_a_reference_number_alone_returns_masked_progress(): void
    {
        $citizen = $this->citizen(['national_id' => '4123456789']);
        $reference = $this->submit($citizen)->json('data.reference_number');

        $response = $this->track(['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.reference_number', $reference)
            ->assertJsonPath('data.status.code', ApplicationStatusCode::Submitted->value)
            ->assertJsonPath('meta.owner_verified', false)
            ->assertJsonStructure([
                'data' => ['service', 'department', 'title', 'progress', 'stage', 'submitted_at', 'due_at', 'status' => ['code', 'name', 'color']],
                'meta' => ['timeline', 'poll_after_seconds'],
            ]);

        // The applicant sees where the case is, not who else has one.
        $this->assertSame('Amina Y***', $response->json('data.applicant_name'));
        $this->assertStringNotContainsString('Yusuf', $response->getContent());
        $this->assertStringNotContainsString('4123456789', $response->getContent());
        $this->assertArrayNotHasKey('applicant_phone', $response->json('data'));
        $this->assertArrayNotHasKey('citizen', $response->json('data'));

        $this->assertCount(1, $response->json('meta.timeline'));
        $this->assertSame(15, $response->json('meta.poll_after_seconds'));
    }

    public function test_the_lookup_is_case_insensitive_on_the_reference(): void
    {
        $reference = $this->submit($this->citizen())->json('data.reference_number');

        $this->track(['reference' => strtolower($reference)])->assertOk();
    }

    public function test_proving_the_citizen_number_unlocks_the_full_name(): void
    {
        $citizen = $this->citizen(['national_id' => '4123456789']);
        $reference = $this->submit($citizen)->json('data.reference_number');

        $this->track(['reference' => $reference, 'national_id' => '4123456789'])
            ->assertOk()
            ->assertJsonPath('meta.owner_verified', true)
            ->assertJsonPath('data.applicant_name', 'Amina Yusuf');

        $this->track(['reference' => $reference, 'national_id' => '4123456780'])
            ->assertOk()
            ->assertJsonPath('meta.owner_verified', false)
            ->assertJsonPath('data.applicant_name', 'Amina Y***');
    }

    public function test_an_unknown_reference_is_a_404_and_is_logged(): void
    {
        $this->track(['reference' => 'PSP-2026-999999'])
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found')
            ->assertJsonPath('message', 'No application matches that tracking number.');

        $this->assertDatabaseHas('audit_logs', ['event' => 'tracking_miss']);

        // A failed lookup carries nothing but the number that was typed in.
        $miss = AuditLog::where('event', 'tracking_miss')->firstOrFail();

        $this->assertSame(['reference' => 'PSP-2026-999999'], $miss->meta);
        $this->assertNull($miss->user_id);
    }

    public function test_internal_officer_notes_stay_out_of_the_public_timeline(): void
    {
        $citizen = $this->citizen();
        $officer = $this->officer();
        $reference = $this->submit($citizen)->json('data.reference_number');
        $application = Application::where('reference_number', $reference)->firstOrFail();
        $application->forceFill(['department_id' => $officer->department_id])->save();

        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => ApplicationStatusCode::UnderReview->value,
            'note' => 'Waiting on the internal registry backlog.',
            'internal_only' => true,
        ])->assertOk();

        $timeline = $this->track(['reference' => $reference])->assertOk()->json('meta.timeline');

        $this->assertCount(1, $timeline);
        $this->assertStringNotContainsString('registry backlog', json_encode($timeline));

        // Public entries name the officer only by first name.
        $this->assertStringNotContainsString($officer->name, json_encode($timeline));
    }

    public function test_a_withdrawn_case_stops_reporting_progress(): void
    {
        $citizen = $this->citizen();
        $reference = $this->submit($citizen)->json('data.reference_number');
        $application = Application::where('reference_number', $reference)->firstOrFail();

        $this->apiAs($citizen, 'delete', "/api/v1/applications/{$application->id}")->assertOk();

        $this->track(['reference' => $reference])
            ->assertOk()
            ->assertJsonPath('data.state', 'withdrawn')
            ->assertJsonPath('message', 'This application was withdrawn by the applicant.');
    }

    public function test_the_reference_format_is_validated_before_the_database_is_touched(): void
    {
        $this->track()->assertUnprocessable()->assertJsonValidationErrors(['reference']);

        $this->track(['reference' => 'PSP 2026/1'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.reference.0', 'Tracking references look like PSP-2026-000123.');

        $this->track(['reference' => 'PSP-2026-000123', 'national_id' => '12'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.national_id.0', 'Enter the full 10 digit citizen number.');
    }

    public function test_the_endpoint_is_rate_limited_to_stop_reference_enumeration(): void
    {
        $reference = $this->submit($this->citizen())->json('data.reference_number');

        for ($lookup = 1; $lookup <= 30; $lookup++) {
            $this->track(['reference' => $reference])->assertOk();
        }

        $this->track(['reference' => $reference])->assertStatus(429);
    }

    public function test_every_successful_lookup_leaves_an_audit_entry(): void
    {
        $reference = $this->submit($this->citizen())->json('data.reference_number');

        $this->track(['reference' => $reference])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'tracked', 'reference_number' => $reference]);
    }
}
