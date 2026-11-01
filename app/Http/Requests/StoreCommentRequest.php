<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $this->user()?->can('comment', $application) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'is_internal' => $this->user()?->isStaff()
                ? ['sometimes', 'boolean']
                : ['sometimes', Rule::in([false, 0, '0'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.min' => 'Your message is too short.',
        ];
    }

    public function isInternal(): bool
    {
        return (bool) $this->input('is_internal', false) && (bool) $this->user()?->isStaff();
    }
}
