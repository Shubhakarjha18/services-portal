<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Admin) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = optional($this->route('service_type'))->id;
        $presence = $this->isMethod('PUT') ? 'sometimes' : 'required';

        return [
            'department_id' => [$presence, 'required', 'integer', Rule::exists('departments', 'id')],
            'name' => [$presence, 'required', 'string', 'min:4', 'max:180'],
            'code' => [
                $presence,
                'required',
                'string',
                'alpha_dash',
                'min:3',
                'max:48',
                Rule::unique('service_types', 'code')->ignore($id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'fee' => ['sometimes', 'numeric', 'min:0', 'max:999999'],
            'sla_days' => ['sometimes', 'integer', 'between:1,365'],
            'required_documents' => ['sometimes', 'nullable', 'array', 'max:12'],
            'required_documents.*' => ['required', 'string', 'min:3', 'max:160'],
            'eligibility_notes' => ['sometimes', 'nullable', 'array', 'max:12'],
            'eligibility_notes.*' => ['required', 'string', 'max:300'],
            'is_public' => ['sometimes', 'boolean'],
            'requires_payment' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required_documents.*.min' => 'Each required document needs a clear name of at least 3 characters.',
        ];
    }

    public function requiredDocuments(): array
    {
        return array_values(array_unique(array_map(
            static fn (string $label): string => trim($label),
            (array) $this->input('required_documents', [])
        )));
    }
}
