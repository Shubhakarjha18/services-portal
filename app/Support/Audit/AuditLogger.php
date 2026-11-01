<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Single write path for the compliance trail. Every entry keeps the actor's
 * name and role so the log stays readable after the user is deleted.
 */
class AuditLogger
{
    /** Attributes never copied into the log. */
    private const REDACTED = [
        'password',
        'remember_token',
        'token',
        'access_token',
        'api_token',
    ];

    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $meta
     */
    public function log(
        string $event,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        array $meta = [],
        ?User $actor = null,
    ): ?AuditLog {
        $actor ??= auth()->user();
        $user = $actor instanceof User ? $actor : null;

        $attributes = [
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'role_name' => $user?->roleName()?->value,
            'url' => $this->request->fullUrl(),
            'method' => $this->request->getMethod(),
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 500),
            'old_values' => $this->sanitize($old),
            'new_values' => $this->sanitize($new),
            'meta' => $this->sanitize($meta),
            'created_at' => now(),
        ];

        if ($subject !== null) {
            $attributes['reference_number'] = $this->referenceFor($subject);
        }

        return AuditLog::create($attributes);
    }

    /**
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $old
     */
    public function modelEvent(string $event, Model $subject, array $new = [], array $old = []): ?AuditLog
    {
        return $this->log($event, $subject, $old, $new);
    }

    public function referenceFor(Model $subject): ?string
    {
        return $subject instanceof AuditReference ? $subject->auditReference() : null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function sanitize(array $values): array
    {
        $clean = array_diff_key($values, array_flip(self::REDACTED));

        return array_map(
            fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value),
            $clean
        );
    }
}
