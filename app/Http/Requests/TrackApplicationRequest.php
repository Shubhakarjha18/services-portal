<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Unauthenticated tracker lookup. The reference number alone is enough to read
 * the public timeline; supplying the citizen number additionally proves the
 * caller owns the case and unlocks the contact details.
 */
class TrackApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reference' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9-]{6,32}$/',
            ],
            'national_id' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reference.regex' => 'Tracking references look like PSP-2026-000123.',
            'national_id.regex' => 'Enter the full 10 digit citizen number.',
        ];
    }

    public function reference(): string
    {
        return mb_strtoupper(trim((string) $this->input('reference')));
    }

    public function nationalId(): ?string
    {
        return $this->filled('national_id') ? (string) $this->input('national_id') : null;
    }
}
