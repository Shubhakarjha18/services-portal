<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login', [
            'demoAccounts' => [
                ['role' => 'Administrator', 'email' => 'admin@portal.local', 'password' => 'password'],
                ['role' => 'Case Officer', 'email' => 'officer@portal.local', 'password' => 'password'],
                ['role' => 'Public User', 'email' => 'citizen@portal.local', 'password' => 'password'],
            ],
            'canRegister' => true,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:filter'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', mb_strtolower((string) $credentials['email']))->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->audit->log(event: 'login_failed', new: ['email' => $credentials['email']]);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        if (! $user->is_active) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'This account has been deactivated.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->log(event: 'login', subject: $user, actor: $user);

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'email' => ['required', 'email:filter', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'national_id' => ['required', 'string', 'size:10', 'regex:/^[0-9]{10}$/', 'unique:users,national_id'],
            'terms' => ['accepted'],
        ], [], ['national_id' => 'citizen number', 'phone' => 'phone number']);

        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => $data['password'],
            'phone' => $data['phone'],
            'national_id' => $data['national_id'],
            'role_id' => Role::where('name', RoleName::Public->value)->value('id'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $this->audit->log(event: 'account_created', subject: $user, new: ['email' => $user->email], actor: $user);

        return redirect()->route('applications.create')
            ->with('success', 'Welcome! Your account is ready — pick a service to start an application.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $this->audit->log(event: 'logout', subject: $user, actor: $user);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Signed out.');
    }
}
