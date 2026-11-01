<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $this->user()?->can('verifyDocument', $document) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([DocumentStatus::Accepted->value, DocumentStatus::Rejected->value])],
            'review_note' => [
                'nullable',
                'string',
                'min:5',
                'max:500',
                Rule::requiredIf(fn () => $this->input('status') === DocumentStatus::Rejected->value),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'review_note.required' => 'Explain why the document was rejected so the applicant knows what to re-upload.',
        ];
    }
}
