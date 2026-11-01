<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'stage' => $this->stage,
            'progress' => $this->progressPercentage(),
            'status' => $this->whenLoaded('status', fn () => [
                'id' => $this->status->id,
                'code' => $this->status->code->value,
                'name' => $this->status->name,
                'color' => $this->status->color,
                'is_terminal' => $this->status->is_terminal,
            ]),
            'service' => $this->whenLoaded('serviceType', fn () => [
                'id' => $this->serviceType->id,
                'name' => $this->serviceType->name,
                'code' => $this->serviceType->code,
                'fee' => (float) $this->serviceType->fee,
            ]),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department?->id,
                'name' => $this->department?->name,
                'code' => $this->department?->code,
            ]),
            'applicant' => [
                'name' => $this->applicant_name,
                'phone' => $this->applicant_phone,
                'address' => $this->when(
                    $request->user()?->isStaff() || $this->citizen_id === $request->user()?->id,
                    $this->applicant_address
                ),
            ],
            'citizen' => $this->whenLoaded('citizen', fn () => $this->citizen?->only(['id', 'name', 'email'])),
            'officer' => $this->whenLoaded('officer', fn () => $this->officer === null ? null : [
                'id' => $this->officer->id,
                'name' => $this->officer->name,
                'email' => $request->user()?->isStaff() ? $this->officer->email : null,
            ]),
            'timestamps' => [
                'submitted_at' => $this->submitted_at?->toIso8601String(),
                'due_at' => $this->due_at?->toIso8601String(),
                'closed_at' => $this->closed_at?->toIso8601String(),
                'last_activity_at' => $this->last_activity_at?->toIso8601String(),
                'created_at' => $this->created_at?->toIso8601String(),
            ],
            'sla' => [
                'days_remaining' => $this->slaDaysRemaining(),
                'is_overdue' => $this->isOverdue(),
            ],
            'counts' => [
                'documents' => $this->whenCounted('documents'),
                'comments' => $this->whenCounted('comments'),
                'transitions' => $this->whenCounted('transitions'),
                'views' => $this->view_count,
            ],
            'next_statuses' => $this->when(
                isset($this->available_transitions),
                fn () => $this->available_transitions
            ),
        ];
    }
}
