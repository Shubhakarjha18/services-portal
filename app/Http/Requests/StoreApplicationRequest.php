<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\RoleName;
use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Application::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'service_type_id' => [
                'required',
                'integer',
                Rule::exists('service_types', 'id')->where('is_public', true),
            ],
            'title' => ['required', 'string', 'min:8', 'max:200'],
            'description' => ['required', 'string', 'min:20', 'max:4000'],
            'applicant_name' => ['required', 'string', 'min:3', 'max:160'],
            'applicant_phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'applicant_address' => ['required', 'string', 'min:10', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ] + $this->priorityRules();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'service_type_id.exists' => 'The selected service is no longer available.',
            'description.min' => 'Please describe your request in at least 20 characters so the officer can assess it.',
            'applicant_address.min' => 'Provide a full mailing address (at least 10 characters).',
            'applicant_phone.regex' => 'Use digits, spaces, brackets or a leading + only.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'service_type_id' => 'service',
            'applicant_phone' => 'contact number',
        ];
    }

    /**
     * Only staff may raise the priority of a case; citizens always submit at
     * normal priority.
     *
     * @return array<string, mixed>
     */
    protected function priorityRules(): array
    {
        if ($this->user()?->hasRole(RoleName::Public)) {
            return [];
        }

        return ['priority' => ['nullable', Rule::in(Priority::values())]];
    }

    public function validatedPriority(): string
    {
        if ($this->user()?->hasRole(RoleName::Public)) {
            return Priority::Normal->value;
        }

        return (string) ($this->input('priority') ?? Priority::Normal->value);
    }
}
