<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ApplicationComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApplicationComment
 */
class CommentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'author' => [
                'id' => $this->author?->id,
                'name' => $this->author?->name,
                'role' => $this->author?->roleName()?->value,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
