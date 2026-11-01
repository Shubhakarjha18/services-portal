<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\ApplicationStatusCode;
use App\Models\Application;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\PortalTestCase;

/**
 * The Inertia surface. These tests check the page contracts the Vue components
 * are written against, plus the same RBAC and workflow rules on session routes.
 */
final class WebPortalTest extends PortalTestCase
{
    public function test_a_guest_cannot_reach_the_portal(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/applications')->assertRedirect('/login');
        $this->post('/applications/1/status')->assertRedirect('/login');
    }

    public function test_the_login_page_offers_the_demo_accounts(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->has('demoAccounts', 3)
                ->where('demoAccounts.0.email', 'admin@portal.local')
            );
    }

    public function test_signing_in_starts_a_session_and_lands_on_the_dashboard(): void
    {
        $citizen = $this->citizen();

        $this->post('/login', ['email' => $citizen->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($citizen);

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard')
                ->where('auth.user.email', $citizen->email)
                ->where('auth.user.role', 'public')
                ->where('view', 'mine')
                ->has('stats.totals')
            );

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_bad_credentials_come_back_as_a_form_error(): void
    {
        $citizen = $this->citizen();

        $this->from('/login')
            ->post('/login', ['email' => $citizen->email, 'password' => 'nope'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_failed']);
    }

    public function test_the_landing_page_is_public_and_lists_the_catalogue(): void
    {
        $services = ServiceType::query()->publicCatalog()->count();

        $this->assertGreaterThan(0, $services);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Public/Home')
                ->has('services', $services)
                ->has('statuses', 10)
                ->where('pollAfter', 15)
            );
    }

    public function test_a_signed_in_user_is_moved_off_the_landing_page(): void
    {
        $this->be($this->citizen())->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_the_web_form_submits_a_case_and_redirects_to_the_tracking_page(): void
    {
        $citizen = $this->citizen();
        $this->be($citizen);

        $response = $this->post('/applications', $this->applicationPayload())->assertRedirect();

        $application = Application::firstOrFail();

        $this->assertSame($citizen->id, $application->citizen_id);
        $this->assertStringStartsWith('PSP-', $application->reference_number);
        $response->assertRedirect(route('applications.show', $application))->assertSessionHas('success');

        $this->get(route('applications.show', $application))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Applications/Show')
                ->where('application.reference', $application->reference_number)
                ->where('application.status.code', ApplicationStatusCode::Submitted->value)
                // A citizen only gets the withdraw action.
                ->has('application.actions', 1)
                ->where('officers', [])
                ->where('can.is_admin', false)
            );
    }

    public function test_validation_failures_on_the_web_form_come_back_as_errors(): void
    {
        $this->be($this->citizen());

        $this->post('/applications', ['title' => 'too short'])->assertSessionHasErrors([
            'service_type_id', 'description', 'applicant_name', 'applicant_phone', 'applicant_address',
        ]);
    }

    public function test_an_illegal_move_comes_back_as_a_flash_error_not_a_crash(): void
    {
        $officer = $this->officer();
        $application = Application::factory()->submitted()
            ->create(['department_id' => $officer->department_id, 'assigned_officer_id' => $officer->id]);

        $this->be($officer);

        $this->post("/applications/{$application->id}/status", ['status' => 'approved'])
            ->assertRedirect()
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'not permitted'));

        $this->assertSame('submitted', $application->fresh()->code()->value);

        // The legal move from the same screen works.
        $this->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/status", [
                'status' => ApplicationStatusCode::UnderReview->value,
                'note' => 'Checking the registry.',
            ])
            ->assertRedirect("/applications/{$application->id}")
            ->assertSessionHas('success');

        $this->assertSame('under_review', $application->fresh()->code()->value);
    }

    public function test_case_officers_cannot_open_the_administration_pages(): void
    {
        $officer = $this->officer();
        $this->be($officer);

        foreach (['/admin/users', '/admin/catalog', '/admin/audit'] as $path) {
            $this->get($path)->assertForbidden();
        }

        $this->be($this->admin());

        $this->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Users')->has('users'));

        $this->get('/admin/audit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Audit'));
    }

    public function test_an_officer_cannot_open_a_case_from_another_department(): void
    {
        $application = Application::factory()->create();

        $this->be($this->officer())->get("/applications/{$application->id}")->assertForbidden();
    }

    public function test_the_queue_page_only_shows_the_cases_the_officer_can_work(): void
    {
        $officer = $this->officer();
        $mine = Application::factory()->underReview()
            ->create(['department_id' => $officer->department_id, 'assigned_officer_id' => $officer->id]);
        $elsewhere = Application::factory()->create();

        $this->be($officer);

        $response = $this->get('/applications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Applications/Index')
                ->where('view', 'queue')
                ->where('applications.total', 1)
                ->where('applications.data.0.reference', $mine->reference_number)
                ->has('filters', 5)
                ->has('statuses')
            );

        $this->assertStringNotContainsString($elsewhere->reference_number, $response->getContent());
    }

    public function test_paperwork_uploaded_through_the_ui_is_stored_privately_and_downloads_back(): void
    {
        Storage::fake('documents');

        $citizen = $this->citizen();
        $application = Application::factory()->forCitizen($citizen)->documentsRequired()->create();

        $this->be($citizen);

        $this->from("/applications/{$application->id}")
            ->post("/applications/{$application->id}/documents", [
                'label' => 'Proof of address',
                'file' => UploadedFile::fake()->createWithContent('lease.pdf', '%PDF-1.4 lease'),
            ])->assertRedirect("/applications/{$application->id}")->assertSessionHas('success');

        $document = $application->documents()->firstOrFail();

        Storage::disk('documents')->assertExists($document->path);

        $this->get(route('portal.documents.download', $document))->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'document_downloaded', 'user_id' => $citizen->id]);
    }

    public function test_a_deactivated_account_is_signed_out_of_the_ui(): void
    {
        $citizen = $this->citizen();
        $this->be($citizen);

        $this->get('/dashboard')->assertOk();

        User::withoutEvents(fn () => $citizen->forceFill(['is_active' => false])->save());

        $this->get('/dashboard')->assertForbidden();

        // Signing out still works so the browser ends on the login screen.
        $this->post('/logout')->assertRedirect('/');
    }

    public function test_the_withdraw_button_soft_deletes_the_case_from_the_ui(): void
    {
        $citizen = $this->citizen();
        $application = Application::factory()->forCitizen($citizen)->submitted()->create();

        $this->be($citizen);

        $this->delete("/applications/{$application->id}")->assertRedirect(route('applications.index'));

        $this->assertSoftDeleted('applications', ['id' => $application->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'application_withdrawn']);
    }
}
