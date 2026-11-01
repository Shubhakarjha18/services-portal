<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Tests\PortalTestCase;

/**
 * POST /api/v1/auth/* and GET /api/v1/auth/me — the Sanctum token surface.
 */
final class AuthTest extends PortalTestCase
{
    public function test_citizen_can_register_and_receives_a_scoped_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Amina Yusuf',
            'email' => 'Amina@Example.COM',
            'password' => 'Passw0rd123',
            'password_confirmation' => 'Passw0rd123',
            'phone' => '+1 555 0100',
            'national_id' => '4123456789',
            'terms' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'amina@example.com')
            ->assertJsonPath('data.user.role.name', RoleName::Public->value)
            ->assertJsonPath('data.abilities.0', 'applications:read:self');

        $user = User::where('email', 'amina@example.com')->firstOrFail();

        $this->assertNotSame('Passw0rd123', $user->password);
        $this->assertTrue(Hash::check('Passw0rd123', $user->password));

        // Registration must never mint staff abilities.
        $this->assertNotContains('users:manage', $response->json('data.abilities'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'account_created', 'user_id' => $user->id]);
    }

    /**
     * The role is decided by the server. A payload claiming to be an admin has
     * to be ignored rather than trusted.
     */
    public function test_registration_cannot_grant_the_admin_role(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Sneaky Applicant',
            'email' => 'sneaky@example.com',
            'password' => 'Passw0rd123',
            'password_confirmation' => 'Passw0rd123',
            'phone' => '+1 555 0111',
            'national_id' => '4123456781',
            'terms' => true,
            'role' => RoleName::Admin->value,
            'role_id' => 1,
        ])->assertCreated();

        $this->assertSame(
            RoleName::Public->value,
            User::where('email', 'sneaky@example.com')->firstOrFail()->roleName()->value
        );
    }

    public function test_registration_validates_the_citizen_number_and_password(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ab',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'short',
            'phone' => 'abc',
            'national_id' => '12',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'phone', 'national_id', 'terms'])
            ->assertJsonPath('errors.national_id.0', 'The citizen number must contain exactly 10 digits.');
    }

    public function test_login_returns_a_bearer_token_and_records_the_event(): void
    {
        $citizen = $this->citizen();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $citizen->email,
            'password' => 'password',
            'device_name' => 'postman',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.id', $citizen->id);

        $this->assertNotNull($citizen->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $citizen->id]);
        $this->assertSame(1, $citizen->tokens()->count());
    }

    public function test_login_failure_is_generic_and_audited(): void
    {
        $citizen = $this->citizen();

        $this->postJson('/api/v1/auth/login', [
            'email' => $citizen->email,
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_credentials')
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('audit_logs', ['event' => 'login_failed']);
    }

    public function test_deactivated_account_cannot_sign_in(): void
    {
        $user = User::factory()->citizen()->inactive()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()->assertJsonPath('code', 'account_disabled');
    }

    public function test_me_requires_a_valid_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer nonsense.token'])
            ->assertUnauthorized();

        $citizen = $this->citizen();

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$this->tokenFor($citizen)])
            ->assertOk()
            ->assertJsonPath('data.email', $citizen->email)
            ->assertJsonPath('data.role.name', RoleName::Public->value);
    }

    public function test_logout_revokes_only_the_presenting_token(): void
    {
        $user = $this->citizen();

        $first = $this->tokenFor($user);
        $second = $user->createToken('other-device', ['applications:read:self'])->plainTextToken;

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => 'Bearer '.$first])
            ->assertOk();

        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$first])->assertUnauthorized();
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$second])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'logout', 'user_id' => $user->id]);
    }

    public function test_refresh_rotates_the_token(): void
    {
        $user = $this->citizen();
        $token = $this->tokenFor($user);

        $new = $this->postJson('/api/v1/auth/refresh', [], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->json('data.token');

        $this->assertNotSame($token, $new);
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $this->getJson('/api/v1/auth/me', ['Authorization' => 'Bearer '.$new])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'token_rotated', 'user_id' => $user->id]);
    }

    public function test_login_is_throttled_per_email_and_ip(): void
    {
        $user = $this->citizen();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_unknown_api_route_returns_a_json_404(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('code', 'route_not_found');
    }

    public function test_audit_trail_never_stores_secrets(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Quiet Registrar',
            'email' => 'quiet@example.com',
            'password' => 'Passw0rd123',
            'password_confirmation' => 'Passw0rd123',
            'phone' => '+1 555 0122',
            'national_id' => '4123456782',
            'terms' => true,
        ])->assertCreated();

        $stored = AuditLog::pluck('new_values')->implode(' ');

        $this->assertStringNotContainsString('Passw0rd123', $stored);
        $this->assertStringNotContainsString('password', $stored);

        // The redaction happens in the writer, not by accident.
        app(AuditLogger::class)->log(
            event: 'probe',
            new: ['email' => 'x@example.com', 'password' => 'PlainSecret123', 'remember_token' => 'abc'],
        );

        $row = AuditLog::where('event', 'probe')->firstOrFail();

        $this->assertSame(['email' => 'x@example.com'], $row->new_values);
    }
}
