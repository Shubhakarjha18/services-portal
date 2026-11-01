<?php

declare(strict_types=1);

namespace App\Support\Concerns;

use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Records created / updated / deleted rows in the audit trail automatically.
 * Column names that never carry meaning in a log are skipped to keep rows small.
 */
trait Auditable
{
    protected array $auditExcept = [
        'created_at',
        'updated_at',
        'deleted_at',
        'remember_token',
        'view_count',
        'last_activity_at',
    ];

    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            app(AuditLogger::class)->modelEvent('created', $model, static::auditDiff($model, $model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->getChanges();

            // getChanges() only carries the new side; pull the previous values
            // from the original attribute snapshot taken before the save.
            $old = array_intersect_key($model->getRawOriginal(), $changes);
            $new = array_intersect_key($model->getAttributes(), $changes);

            $old = static::auditDiff($model, $old);
            $new = static::auditDiff($model, $new);

            if ($old === [] && $new === []) {
                return;
            }

            app(AuditLogger::class)->modelEvent('updated', $model, $new, $old);
        });

        static::deleted(function (Model $model) {
            app(AuditLogger::class)->modelEvent('deleted', $model, [], static::auditDiff($model, $model->getOriginal()));
        });
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected static function auditDiff(Model $model, array $values): array
    {
        if (! method_exists($model, 'getAuditExcept')) {
            return $values;
        }

        return array_diff_key($values, array_flip($model->getAuditExcept()));
    }

    /** @return array<int, string> */
    public function getAuditExcept(): array
    {
        return $this->auditExcept;
    }
}
