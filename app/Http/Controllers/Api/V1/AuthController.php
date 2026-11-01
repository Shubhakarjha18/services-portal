<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const TOKEN_TTL_DAYS = 7;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * POST /api/v1/auth/register — public self-service signup only.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => mb_strtolower((string) $request->input('email')),
            'password' => $request->input('password'),
            'phone' => $request->input('phone'),
            'national_id' => $request->input('national_id'),
            'role_id' => Role::where('name', RoleName::Public->value)->value('id'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->load(['role', 'department', 'permissions']);

        $this->audit->log(event: 'account_created', subject: $user, new: ['email' => $user->email], actor: $user);

        return $this->issueToken($request, $user, 'Account created.', 201);
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with(['role', 'department', 'permissions'])
            ->where('email', mb_strtolower((string) $request->input('email')))
            ->first();

        // A single generic message and a dummy hash keep user enumeration and
        // timing differences out of the login endpoint.
        $credentialsMatch = $user !== null
            && Hash::check((string) $request->input('password'), $user->password);

        if ($user === null) {
            Hash::make((string) $request->input('password'));
        }

        if (! $credentialsMatch || $user === null) {
            $this->audit->log(
                event: 'login_failed',
                new: ['email' => $request->input('email')],
            );

            return response()->json([
                'message' => 'These credentials do not match our records.',
                'code' => 'invalid_credentials',
            ], 422);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'This account has been deactivated. Contact the portal administrator.',
                'code' => 'account_disabled',
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $this->audit->log(event: 'login', subject: $user, actor: $user);

        return $this->issueToken($request, $user, 'Signed in.');
    }

    /**
     * POST /api/v1/auth/logout — revokes the presenting token only.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        $this->audit->log(
            event: 'logout',
            subject: $request->user(),
            meta: ['token_id' => $token?->id],
            actor: $request->user(),
        );

        if (method_exists($token, 'delete')) {
            $token->delete();
        } else {
            $request->user()->tokens()->delete();
        }

        return response()->json(['message' => 'Signed out.']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): UserResource
    {
        $user = $request->user()->load(['role', 'department', 'permissions']);

        return new UserResource($user);
    }

    /**
     * POST /api/v1/auth/tokens — rotate the token without asking for the password again.
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $current = $user->currentAccessToken();

        $this->audit->log(event: 'token_rotated', subject: $user, actor: $user);

        $token = $user->createToken(
            (string) ($current->name ?? 'api'),
            $this->abilitiesFor($user),
            now()->addDays(self::TOKEN_TTL_DAYS)
        );

        if (method_exists($current, 'delete')) {
            $current->delete();
        }

        return response()->json([
            'message' => 'Token rotated.',
            'data' => [
                'token' => $token->plainTextToken,
                'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS)->toIso8601String(),
            ],
        ]);
    }

    private function issueToken(Request $request, User $user, string $message, int $status = 200): JsonResponse
    {
        $plainText = $user->createToken(
            (string) $request->input('device_name', 'api-client'),
            $this->abilitiesFor($user),
            now()->addDays(self::TOKEN_TTL_DAYS)
        )->plainTextToken;

        return response()->json([
            'message' => $message,
            'data' => [
                'token' => $plainText,
                'token_type' => 'Bearer',
                'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS)->toIso8601String(),
                'abilities' => $this->abilitiesFor($user),
                'user' => new UserResource($user->loadMissing(['role', 'department', 'permissions'])),
            ],
        ], $status);
    }

    /**
     * Tokens carry coarse abilities so a leaked citizen token cannot be used
     * against staff endpoints even before the policy layer runs.
     *
     * @return array<int, string>
     */
    private function abilitiesFor(User $user): array
    {
        return match ($user->roleName()) {
            RoleName::Admin => ['applications:read', 'applications:write', 'applications:decide', 'applications:assign', 'users:manage', 'audit:read', 'catalog:manage'],
            RoleName::Officer => ['applications:read', 'applications:write', 'applications:assign', 'audit:read:self'],
            default => ['applications:read:self', 'applications:write:self'],
        };
    }
}
