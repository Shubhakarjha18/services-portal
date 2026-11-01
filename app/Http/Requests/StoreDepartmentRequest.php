<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by POST and PUT on departments; partial updates use `sometimes`.
 */
class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Admin) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = optional($this->route('department'))->id;
        $presence = $this->isMethod('PUT') ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'required', 'string', 'min:3', 'max:160'],
            'code' => [
                $presence,
                'required',
                'string',
                'alpha_dash',
                'min:2',
                'max:32',
                Rule::unique('departments', 'code')->ignore($id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'contact_email' => ['sometimes', 'nullable', 'email:filter', 'max:255'],
            'default_sla_days' => ['sometimes', 'integer', 'between:1,365'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.unique' => 'That department code is already in use.',
        ];
    }
}
