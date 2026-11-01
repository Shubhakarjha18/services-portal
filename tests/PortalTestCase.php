<?php

namespace Tests;

use App\Models\Department;
use App\Models\ServiceType;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Testing\TestResponse;

/**
 * Shared setup for the portal tests.
 *
 * Roles, permissions and the status/service catalogue are data-driven, so every
 * test starts from the same reference rows the seeder produces in development.
 */
abstract class PortalTestCase extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $tokens = [];

    private ?Authenticatable $sessionUser = null;

    private ?string $sessionGuard = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, CatalogSeeder::class]);
    }

    /**
     * A test boots the application once but can issue many requests. Guards are
     * singletons, so a revoked bearer token would still resolve its user and an
     * actingAs() session user would be dropped. Real HTTP rebuilds the auth
     * state per request — do the same here.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $auth = $this->app->make('auth');
        $auth->forgetGuards();

        if ($this->sessionUser !== null) {
            $auth->guard($this->sessionGuard)->setUser($this->sessionUser);
            $auth->shouldUse($this->sessionGuard);
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    public function be($user, $guard = null)
    {
        $this->sessionUser = $user;
        $this->sessionGuard = $guard;

        return parent::be($user, $guard);
    }

    protected function citizen(array $attributes = []): User
    {
        return User::factory()->citizen()->create($attributes);
    }

    protected function officer(?Department $department = null, array $attributes = []): User
    {
        return User::factory()->officer()->create([
            'department_id' => ($department ?? Department::first())->id,
            ...$attributes,
        ]);
    }

    protected function admin(array $attributes = []): User
    {
        return User::factory()->admin()->create([
            'department_id' => Department::first()->id,
            ...$attributes,
        ]);
    }

    /** A public service from the seeded catalogue. */
    protected function publicService(): ServiceType
    {
        return ServiceType::query()->where('is_public', true)->firstOrFail();
    }

    /**
     * A payload that passes StoreApplicationRequest.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function applicationPayload(array $overrides = []): array
    {
        return [
            'service_type_id' => $this->publicService()->id,
            'title' => 'Renewal of my national identity card',
            'description' => 'My card expired last month and I need it for employment paperwork.',
            'applicant_name' => 'Amina Yusuf',
            'applicant_phone' => '+1 555 0100',
            'applicant_address' => '12 Riverside Road, Block C, Apt 4',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function submit(User $citizen, array $overrides = []): TestResponse
    {
        return $this->apiAs($citizen, 'post', '/api/v1/applications', $this->applicationPayload($overrides));
    }

    /**
     * Real bearer token from the login endpoint. Cached per user so a test can
     * make many authenticated calls without tripping the login throttle.
     */
    protected function tokenFor(User $user, string $password = 'password'): string
    {
        if (isset($this->tokens[$user->id])) {
            return $this->tokens[$user->id];
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
            'device_name' => 'phpunit',
        ]);

        $token = Arr::get($response->json(), 'data.token');

        $this->assertNotNull($token, "Login for {$user->email} failed: ".$response->getContent());

        return $this->tokens[$user->id] = $token;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $headers
     * @return TestResponse
     */
    protected function apiAs(User $user, string $method, string $uri, array $data = [], array $headers = [])
    {
        $call = [
            'get' => 'getJson',
            'post' => 'postJson',
            'put' => 'putJson',
            'patch' => 'patchJson',
            'delete' => 'deleteJson',
        ][$method];

        $authorized = [
            'Authorization' => 'Bearer '.$this->tokenFor($user),
            ...$headers,
        ];

        // Only getJson() takes headers as the second argument; deleteJson()
        // accepts a payload in that position, like the other verbs.
        return $method === 'get'
            ? $this->{$call}($uri, $authorized)
            : $this->{$call}($uri, $data, $authorized);
    }
}
