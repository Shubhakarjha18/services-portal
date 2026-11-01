<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApplicationDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationDocument
 */
class DocumentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_kb' => (int) round($this->size_bytes / 1024),
            'checksum' => $this->checksum,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'review_note' => $request->user()?->isStaff() ? $this->review_note : null,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'uploaded_by' => $this->uploader?->only(['id', 'name']),
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => $request->user()?->isStaff() || $this->uploaded_by === $request->user()?->id
                ? route('api.documents.download', $this->resource)
                : null,
        ];
    }
}
