<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Admin) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $target = $this->route('user');
        $targetId = $target instanceof User ? $target->id : null;
        $actorId = $this->user()?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:160'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email:filter',
                'max:255',
                Rule::unique('users', 'email')->ignore($targetId),
            ],
            'password' => ['sometimes', 'required', Password::min(8)->letters()->numbers()],
            'role_id' => ['sometimes', 'required', 'integer', Rule::exists('roles', 'id')],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')],
            'phone' => ['sometimes', 'nullable', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'is_active' => ['sometimes', 'boolean', function ($attribute, $value, $fail) use ($target, $actorId): void {
                // An admin must not be able to lock themselves out of the portal.
                if ($target instanceof User
                    && $actorId === $target->id
                    && filter_var($value, FILTER_VALIDATE_BOOLEAN) === false) {
                    $fail('You cannot deactivate your own account.');
                }
            }],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function attributes(): array
    {
        return ['role_id' => 'role'];
    }
}
