<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Admin) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:160'],
            'email' => ['required', 'string', 'email:filter', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
            'department_id' => [
                Rule::requiredIf(fn () => $this->selectedRoleIsStaff()),
                'nullable',
                Rule::exists('departments', 'id')->where('is_active', true),
            ],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'national_id' => ['nullable', 'string', 'regex:/^[0-9]{10}$/', 'unique:users,national_id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'role_id.exists' => 'Unknown role.',
            'department_id.exists' => 'Pick an active department.',
            'department_id.required' => 'Staff accounts must belong to a department.',
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->input('is_active', true);
    }

    protected function selectedRoleIsStaff(): bool
    {
        $name = Role::find($this->input('role_id'))?->name;

        return $name !== null && $name->isStaff();
    }
}
