<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ApplicationStatusCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The single endpoint that moves a case along the workflow. Validation here is
 * deliberately thin about business rules: config/portal.php plus the workflow
 * service are the source of truth, so a bad transition returns a clear 422.
 */
class TransitionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $this->user()?->can('transition', [$application, (string) $this->input('status')]) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(ApplicationStatusCode::values()),
            ],
            'note' => ['nullable', 'string', 'min:5', 'max:500'],
            'internal_only' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'note.min' => 'Add a short explanation (at least 5 characters) so the applicant and the audit trail both record why.',
            'status.in' => 'That status is not part of the portal workflow.',
        ];
    }

    public function status(): string
    {
        return (string) $this->input('status');
    }

    public function note(): ?string
    {
        return $this->filled('note') ? (string) $this->input('note') : null;
    }

    public function internalOnly(): bool
    {
        return (bool) $this->input('internal_only', false);
    }
}
