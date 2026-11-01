<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Application;
use Illuminate\Foundation\Http\FormRequest;

class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->application()) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:8', 'max:200'],
            'description' => ['sometimes', 'required', 'string', 'min:20', 'max:4000'],
            'applicant_name' => ['sometimes', 'required', 'string', 'min:3', 'max:160'],
            'applicant_phone' => ['sometimes', 'required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'applicant_address' => ['sometimes', 'required', 'string', 'min:10', 'max:500'],
            'priority' => $this->isStaff()
                ? ['sometimes', 'required', 'string', 'in:low,normal,high,urgent']
                : ['prohibited'],
        ];
    }

    public function application(): Application
    {
        return $this->route('application');
    }

    protected function isStaff(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'priority.prohibited' => 'Only portal staff can change the priority of an application.',
        ];
    }
}
