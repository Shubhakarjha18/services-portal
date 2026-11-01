<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApplicationTransition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationTransition
 */
class TimelineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->toStatus?->code->value,
            'status_name' => $this->toStatus?->name,
            'color' => $this->toStatus?->color,
            'from_status' => $this->fromStatus?->code->value,
            'note' => $this->visible_to_citizen || $request->user()?->isStaff() ? $this->note : null,
            'actor' => [
                'name' => $this->performed_by_name,
                'is_system' => $this->performed_by === null,
            ],
            'stage' => $this->stage,
            'duration_seconds' => $this->duration_seconds,
            'timestamp' => $this->created_at?->toIso8601String(),
        ];
    }
}
