<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'subject_type' => class_basename((string) $this->auditable_type),
            'subject_id' => $this->auditable_id,
            'reference_number' => $this->reference_number,
            'actor' => [
                'id' => $this->user_id,
                'name' => $this->user_name,
                'role' => $this->role_name,
            ],
            'request' => [
                'method' => $this->method,
                'url' => $this->url,
                'ip' => $this->ip_address,
                'user_agent' => $this->user_agent,
            ],
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
