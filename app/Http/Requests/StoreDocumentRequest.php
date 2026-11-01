<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $this->user()?->can('uploadDocument', $application) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'min:3', 'max:160'],
            'file' => [
                'required',
                'file',
                'max:'.config('portal.documents.max_kilobytes'),
                'mimes:'.implode(',', config('portal.documents.allowed_mimes', [])),
            ],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.max' => 'The document must be smaller than '.round(config('portal.documents.max_kilobytes') / 1024, 1).' MB.',
            'file.mimes' => 'Allowed formats: '.strtoupper(implode(', ', config('portal.documents.allowed_mimes', []))).'.',
            'label.required' => 'Select which document you are uploading.',
        ];
    }

    public function documentLabel(): string
    {
        return trim((string) $this->input('label'));
    }
}
