<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\Department;
use App\Models\Role;
use App\Models\ServiceType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\PortalTestCase;

/**
 * The administration surface: user management, the service catalogue and the
 * read-only compliance views. Every route here is guarded twice — once by the
 * token abilities and once by the role middleware.
 */
final class AdministrationTest extends PortalTestCase
{
    public function test_staff_routes_are_invisible_to_everyone_else(): void
    {
        $routes = ['/api/v1/users', '/api/v1/audit-logs', '/api/v1/dashboard/workload', '/api/v1/roles'];

        foreach ($routes as $route) {
            $this->getJson($route)->assertUnauthorized();
        }

        foreach ($routes as $route) {
            $this->apiAs($this->citizen(), 'get', $route)->assertForbidden();
        }

        // Officers get the audit trail and the workload report, never user admin.
        $officer = $this->officer();
        $this->apiAs($officer, 'get', '/api/v1/audit-logs')->assertOk();
        $this->apiAs($officer, 'get', '/api/v1/dashboard/workload')->assertOk();
        $this->apiAs($officer, 'get', '/api/v1/users')->assertForbidden();
        $this->apiAs($officer, 'post', '/api/v1/departments', ['name' => 'X', 'code' => 'X'])->assertForbidden();
    }

    public function test_a_token_without_the_admin_ability_is_refused_even_for_an_admin(): void
    {
        $admin = $this->admin();

        // Defence in depth: the role middleware passes, the ability check does not.
        $limited = $admin->createToken('read-only', ['applications:read'])->plainTextToken;

        $this->getJson('/api/v1/users', ['Authorization' => 'Bearer '.$limited])
            ->assertForbidden()
            ->assertJsonPath('code', 'http_error');

        $this->getJson('/api/v1/applications', ['Authorization' => 'Bearer '.$limited])->assertOk();
    }

    public function test_an_admin_creates_a_staff_account_and_it_is_hashed_and_audited(): void
    {
        $admin = $this->admin();
        $officerRole = Role::where('name', RoleName::Officer->value)->firstOrFail();

        $this->apiAs($admin, 'post', '/api/v1/users', [
            'name' => 'New Case Officer',
            'email' => 'new.officer@example.gov',
            'password' => 'Str0ngPassphrase',
            'role_id' => $officerRole->id,
        ])->assertUnprocessable()->assertJsonPath('errors.department_id.0', 'Staff accounts must belong to a department.');

        $response = $this->apiAs($admin, 'post', '/api/v1/users', [
            'name' => 'New Case Officer',
            'email' => 'New.Officer@Example.GOV',
            'password' => 'Str0ngPassphrase',
            'role_id' => $officerRole->id,
            'department_id' => $admin->department_id,
        ])->assertCreated()->assertJsonPath('data.role.name', RoleName::Officer->value);

        $created = User::where('email', 'new.officer@example.gov')->firstOrFail();

        $this->assertTrue(Hash::check('Str0ngPassphrase', $created->password));
        $this->assertStringNotContainsString('Str0ngPassphrase', $response->getContent());
        $this->assertDatabaseHas('audit_logs', ['event' => 'user_created', 'user_id' => $admin->id]);
    }

    public function test_deactivating_an_account_revokes_its_tokens(): void
    {
        $admin = $this->admin();
        $citizen = $this->citizen();
        $token = $this->tokenFor($citizen);

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])->assertOk();

        $this->apiAs($admin, 'put', "/api/v1/users/{$citizen->id}", [
            'name' => $citizen->name,
            'email' => $citizen->email,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $this->assertSame(0, $citizen->fresh()->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'user_updated', 'user_id' => $admin->id]);
    }

    public function test_an_admin_cannot_lock_themselves_out(): void
    {
        $admin = $this->admin();

        $this->apiAs($admin, 'put', "/api/v1/users/{$admin->id}", ['is_active' => false])
            ->assertUnprocessable()->assertJsonPath('errors.is_active.0', 'You cannot deactivate your own account.');

        $this->apiAs($admin, 'delete', "/api/v1/users/{$admin->id}")
            ->assertUnprocessable()->assertJsonPath('message', 'You cannot remove your own account.');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_removal_deactivates_instead_of_deleting_because_the_trail_needs_the_name(): void
    {
        $admin = $this->admin();
        $officer = $this->officer();

        $response = $this->apiAs($admin, 'delete', "/api/v1/users/{$officer->id}")->assertOk();

        $this->assertSame("{$officer->name} was deactivated.", $response->json('message'));

        $this->assertDatabaseHas('users', ['id' => $officer->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user_deactivated']);
        $this->assertSame(0, $officer->fresh()->tokens()->count());
    }

    public function test_admins_manage_the_service_catalogue(): void
    {
        $admin = $this->admin();
        $department = Department::first();

        $this->apiAs($admin, 'post', '/api/v1/departments', [
            'name' => 'Digital Services',
            'code' => $department->code,
        ])->assertUnprocessable()->assertJsonPath('errors.code.0', 'That department code is already in use.');

        $this->apiAs($admin, 'post', '/api/v1/departments', [
            'name' => 'Digital Services',
            'code' => 'DS',
            'default_sla_days' => 12,
        ])->assertCreated()->assertJsonPath('data.code', 'DS');

        $created = Department::where('code', 'DS')->firstOrFail();

        $this->apiAs($admin, 'post', '/api/v1/services', [
            'department_id' => $created->id,
            'name' => 'Digital signature registration',
            'code' => 'DS-SIGN',
            'fee' => 15.5,
            'sla_days' => 5,
            'required_documents' => ['Citizen number', 'Email address'],
        ])->assertCreated()->assertJsonPath('data.department.code', 'DS');

        $service = ServiceType::where('code', 'DS-SIGN')->firstOrFail();

        $this->assertSame(['Citizen number', 'Email address'], $service->required_documents);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => Department::class, 'user_id' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => ServiceType::class, 'user_id' => $admin->id]);

        // Renaming a service does not have to resubmit its code.
        $this->apiAs($admin, 'put', "/api/v1/services/{$service->id}", ['name' => 'Digital signature registry'])
            ->assertOk()
            ->assertJsonPath('data.code', 'DS-SIGN')
            ->assertJsonPath('data.name', 'Digital signature registry');

        $this->assertDatabaseHas('audit_logs', ['event' => 'updated', 'auditable_type' => ServiceType::class]);
    }

    public function test_hidden_services_never_appear_in_the_public_catalogue(): void
    {
        $admin = $this->admin();
        $hidden = ServiceType::factory()->hidden()->create(['department_id' => Department::first()->id]);

        $citizenCodes = collect($this->apiAs($this->citizen(), 'get', '/api/v1/services')->assertOk()->json('data'))
            ->pluck('code');

        $this->assertFalse($citizenCodes->contains($hidden->code));

        $staffCodes = collect($this->apiAs($admin, 'get', '/api/v1/services')->assertOk()->json('data'))->pluck('code');
        $this->assertTrue($staffCodes->contains($hidden->code));
    }

    public function test_an_officer_only_reads_the_audit_entries_that_concern_their_cases(): void
    {
        $officer = $this->officer();
        $admin = $this->admin();

        // An administration event the officer must never see.
        $this->apiAs($admin, 'post', '/api/v1/users', [
            'name' => 'Records Clerk',
            'email' => 'records.clerk@example.gov',
            'password' => 'passw0rd123',
            'role_id' => Role::where('name', RoleName::Officer->value)->value('id'),
            'department_id' => $officer->department_id,
        ])->assertCreated();

        $application = Application::factory()->underReview()->create([
            'department_id' => $officer->department_id,
            'assigned_officer_id' => $officer->id,
        ]);

        $this->apiAs($officer, 'post', "/api/v1/applications/{$application->id}/status", [
            'status' => 'in_progress',
            'note' => 'Registry started the extract.',
        ])->assertOk();

        $private = $this->apiAs($admin, 'get', '/api/v1/audit-logs?event=user_created')->assertOk()->json('data');
        $this->assertNotEmpty($private, 'Admins read the whole trail.');

        $forOfficer = $this->apiAs($officer, 'get', '/api/v1/audit-logs')->assertOk()->json('data');

        $this->assertNotEmpty($forOfficer);
        $this->assertEmpty(array_filter(
            $forOfficer,
            fn (array $row) => $row['event'] === 'user_created'
        ), 'A status officer must not read administration events.');

        $entryId = collect($private)->firstWhere('event', 'user_created')['id'];

        $this->apiAs($officer, 'get', "/api/v1/audit-logs/{$entryId}")->assertForbidden();
        $this->apiAs($admin, 'get', "/api/v1/audit-logs/{$entryId}")->assertOk();
    }

    public function test_the_export_endpoint_returns_rows_with_a_cap(): void
    {
        $admin = $this->admin();
        $officer = $this->officer();

        $this->apiAs($admin, 'post', '/api/v1/users', [
            'name' => 'Archives Clerk',
            'email' => 'archives.clerk@example.gov',
            'password' => 'passw0rd123',
            'role_id' => Role::where('name', RoleName::Officer->value)->value('id'),
            'department_id' => $officer->department_id,
        ])->assertCreated();

        $export = $this->apiAs($admin, 'get', '/api/v1/audit-logs/export?event=login')
            ->assertOk()
            ->assertJsonPath('meta.truncated_at', 5000);

        $this->assertContains($export->json('meta.exported'), [0, 1]);
        $this->assertEmpty(array_filter(
            $export->json('data'),
            fn (array $row) => $row['event'] !== 'login'
        ), 'The export honours the same filters as the list.');

        $staffExport = $this->apiAs($officer, 'get', '/api/v1/audit-logs/export')->assertOk();

        $this->assertEmpty(array_filter(
            $staffExport->json('data'),
            fn (array $row) => $row['event'] === 'user_created'
        ), 'An officer cannot export administration events.');
    }

    public function test_the_roles_lookup_tells_the_ui_which_roles_need_a_department(): void
    {
        $roles = collect($this->apiAs($this->admin(), 'get', '/api/v1/roles')->assertOk()->json('data'));

        $this->assertCount(3, $roles);
        $this->assertFalse($roles->firstWhere('name', RoleName::Public->value)['requires_department']);
        $this->assertTrue($roles->firstWhere('name', RoleName::Officer->value)['requires_department']);
    }
}
