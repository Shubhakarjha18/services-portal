<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApplicationStatus;
use App\Models\Department;
use App\Models\ServiceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Department|ServiceType|ApplicationStatus
 */
class LookupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return match (true) {
            $this->resource instanceof Department => [
                'id' => $this->id,
                'name' => $this->name,
                'code' => $this->code,
                'description' => $this->description,
                'contact_email' => $this->contact_email,
                'default_sla_days' => $this->default_sla_days,
                'is_active' => $this->is_active,
            ],
            $this->resource instanceof ServiceType => [
                'id' => $this->id,
                'name' => $this->name,
                'code' => $this->code,
                'description' => $this->description,
                'fee' => (float) $this->fee,
                'sla_days' => $this->sla_days,
                'required_documents' => $this->requiredDocumentLabels(),
                'eligibility_notes' => $this->eligibility_notes,
                'requires_payment' => $this->requires_payment,
                'is_public' => $this->is_public,
                'department' => $this->whenLoaded('department', fn () => [
                    'id' => $this->department?->id,
                    'name' => $this->department?->name,
                    'code' => $this->department?->code,
                ]),
            ],
            default => [
                'id' => $this->id,
                'code' => $this->code?->value,
                'name' => $this->name,
                'description' => $this->description,
                'color' => $this->color,
                'sort_order' => $this->sort_order,
                'is_terminal' => $this->is_terminal,
                'counts_as_open' => $this->counts_as_open,
            ],
        };
    }
}
