<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::query()
            ->with(['role:id,name,display_name', 'department:id,name,code'])
            ->when($request->filled('role'), fn (Builder $q) => $q->whereHas(
                'role',
                fn (Builder $r) => $r->where('name', (string) $request->input('role'))
            ))
            ->when($request->filled('department_id'), fn (Builder $q) => $q->where('department_id', (int) $request->input('department_id')))
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $like = '%'.trim((string) $request->input('q')).'%';

                $q->where(fn (Builder $inner) => $inner->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->paginate(min(max((int) $request->input('per_page', 25), 1), 100))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            ...$request->validated(),
            'is_active' => $request->isActive(),
            'email_verified_at' => now(),
        ]);

        $this->audit->log(
            event: 'user_created',
            subject: $user,
            new: ['email' => $user->email, 'role' => $user->roleName()?->value],
            actor: $request->user(),
        );

        return (new UserResource($user->load(['role:id,name,display_name', 'department:id,name,code'])))
            ->additional(['message' => 'Account created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['role:id,name,display_name', 'department:id,name,code']));
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $before = ['is_active' => $user->is_active, 'role_id' => $user->role_id];

        $user->fill($request->validated())->save();

        // Disabling an account must not leave a live token behind.
        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        $this->audit->log(
            event: 'user_updated',
            subject: $user,
            old: $before,
            new: ['is_active' => $user->is_active, 'role_id' => $user->role_id],
            actor: $request->user(),
        );

        return new UserResource($user->load(['role:id,name,display_name', 'department:id,name,code']));
    }

    /**
     * Staff accounts are deactivated rather than deleted: their name has to
     * stay attached to every case and audit entry they produced.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot remove your own account.'], 422);
        }

        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        $this->audit->log(
            event: 'user_deactivated',
            subject: $user,
            old: ['is_active' => true],
            new: ['is_active' => false],
            actor: $request->user(),
        );

        return response()->json(['message' => "{$user->name} was deactivated."]);
    }

    /** GET /api/v1/roles — needed by the admin user form. */
    public function roles(): JsonResponse
    {
        return response()->json([
            'data' => Role::orderByDesc('level')
                ->get(['id', 'name', 'display_name', 'level'])
                ->map(fn (Role $role) => [
                    'id' => $role->id,
                    'name' => $role->name->value,
                    'display_name' => $role->display_name,
                    'level' => $role->level,
                    'requires_department' => $role->name !== RoleName::Public,
                ]),
        ]);
    }
}
