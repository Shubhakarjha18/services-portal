<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RoleName;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props available to every Vue page: who the user is, what they may do,
     * and the flash queue used after redirects.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $user?->loadMissing(['role', 'department', 'permissions']);
        $role = $user?->roleName();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'national_id' => $user->national_id,
                    'role' => $role?->value,
                    'role_label' => $role?->label(),
                    'department' => $user->department?->only(['id', 'name', 'code']),
                    'permissions' => $user->permissions->pluck('name')->all(),
                ],
            ],
            'can' => [
                'is_admin' => $role === RoleName::Admin,
                'is_officer' => $role === RoleName::Officer,
                'is_public' => $role === RoleName::Public,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'appName' => config('app.name'),
        ]);
    }
}
