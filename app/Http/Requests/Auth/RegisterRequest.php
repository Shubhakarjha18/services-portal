<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Self-service registration. The role is never taken from the payload: new
 * accounts are always Public Users and staff accounts are created by an admin.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'email' => ['required', 'string', 'email:filter', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'national_id' => ['required', 'string', 'regex:/^[0-9]{10}$/', 'unique:users,national_id'],
            'terms' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'national_id.regex' => 'The citizen number must contain exactly 10 digits.',
            'national_id.unique' => 'This citizen number is already registered.',
            'phone.regex' => 'Use digits, spaces, brackets or a leading + only.',
            'terms.accepted' => 'You must accept the terms of service to register.',
        ];
    }
}
