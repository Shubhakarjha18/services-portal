<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignOfficerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign', Application::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'officer_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('is_active', true)
                    ->where('role_id', Role::where('name', RoleName::Officer->value)->value('id')),
            ],
            'reason' => ['nullable', 'string', 'min:3', 'max:500'],
            'within_department' => [
                // An admin may cross-assign; everyone else stays in department.
                Rule::when($this->user()?->hasRole(RoleName::Admin), ['nullable', 'boolean']),
            ],
        ];
    }

    public function officerId(): int
    {
        return (int) $this->input('officer_id');
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'officer_id.exists' => 'The selected officer is inactive or does not hold the Case Officer role.',
        ];
    }
}
