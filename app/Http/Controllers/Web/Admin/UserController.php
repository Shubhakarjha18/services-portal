<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account administration. Everything here is admin-only (role middleware) and
 * every mutation writes an audit row.
 */
class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'role' => (string) $request->input('role', ''),
            'department' => (string) $request->input('department', ''),
            'status' => (string) $request->input('status', ''),
        ];

        $users = User::query()
            ->with(['role:id,name,display_name', 'department:id,name,code'])
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $like = '%'.$filters['q'].'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->when($filters['role'] !== '', fn ($q) => $q->whereHas('role', fn ($r) => $r->where('name', $filters['role'])))
            ->when($filters['department'] !== '', fn ($q) => $q->where('department_id', (int) $filters['department']))
            ->when($filters['status'] === 'active', fn ($q) => $q->where('is_active', true))
            ->when($filters['status'] === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Admin/Users', [
            'users' => [
                'data' => $users->getCollection()->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'national_id' => $user->national_id,
                    'role' => $user->role?->name->value,
                    'role_label' => $user->role?->display_name,
                    'department' => $user->department?->name,
                    'department_id' => $user->department_id,
                    'is_active' => $user->is_active,
                    'last_login_at' => $user->last_login_at?->diffForHumans(),
                    'created_at' => $user->created_at?->toDateString(),
                    'is_self' => $user->id === $request->user()->id,
                ])->all(),
                'links' => ['prev' => $users->previousPageUrl(), 'next' => $users->nextPageUrl()],
                'total' => $users->total(),
                'page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
            'filters' => $filters,
            'roles' => Role::orderBy('level')->get(['id', 'name', 'display_name'])
                ->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name->value, 'label' => $role->display_name]),
            'departments' => Department::where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn (Department $department) => ['id' => $department->id, 'name' => $department->name, 'code' => $department->code]),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            ...$data,
            'is_active' => $request->isActive(),
            'email_verified_at' => now(),
        ]);

        $this->audit->log(
            event: 'user_created',
            subject: $user,
            new: ['email' => $user->email, 'role' => $user->role?->name?->value],
            actor: $request->user(),
        );

        return back()->with('success', "{$user->name} can now sign in.");
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $before = $user->only(['role_id', 'department_id', 'is_active', 'email']);

        $user->update($request->validated());
        $user->refresh()->tokens()->delete();

        $this->audit->log(
            event: 'user_updated',
            subject: $user,
            old: $before,
            new: $user->only(['role_id', 'department_id', 'is_active', 'email']),
            actor: $request->user(),
        );

        return back()->with('success', 'Account updated. Existing sessions and tokens were revoked.');
    }
}
