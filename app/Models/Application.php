<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ApplicationStatusCode;
use App\Enums\Priority;
use App\Enums\RoleName;
use App\Support\Audit\AuditReference;
use App\Support\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Application extends Model implements AuditReference
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'reference_number',
        'service_type_id',
        'department_id',
        'citizen_id',
        'assigned_officer_id',
        'application_status_id',
        'title',
        'description',
        'applicant_name',
        'applicant_phone',
        'applicant_address',
        'priority',
        'stage',
        'submitted_at',
        'due_at',
        'closed_at',
        'last_activity_at',
    ];

    protected $casts = [
        'priority' => Priority::class,
        'stage' => 'integer',
        'view_count' => 'integer',
        'submitted_at' => 'datetime',
        'due_at' => 'datetime',
        'closed_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function auditReference(): ?string
    {
        return $this->reference_number;
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function citizen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'citizen_id');
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ApplicationStatus::class, 'application_status_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ApplicationComment::class);
    }

    /** The immutable timeline rendered by the public tracker. */
    public function transitions(): HasMany
    {
        return $this->hasMany(ApplicationTransition::class);
    }

    public function code(): ?ApplicationStatusCode
    {
        return $this->status?->code;
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('application_status_id', function ($sub) {
            $sub->select('id')
                ->from('application_statuses')
                ->where('counts_as_open', true);
        });
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    /** Restrict a query to rows the given user is allowed to read. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(RoleName::Admin)) {
            return $query;
        }

        if ($user->hasRole(RoleName::Officer)) {
            // Officers see every case belonging to their department; the queue
            // view then narrows it down to their own workload.
            return $query->where('department_id', $user->department_id);
        }

        return $query->where('citizen_id', $user->id);
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at->isPast()
            && ! $this->code()?->isTerminal();
    }

    public function slaDaysRemaining(): ?int
    {
        if ($this->due_at === null) {
            return null;
        }

        return (int) round(now()->diffInDays($this->due_at, false));
    }

    /** 0-100 completion estimate driven by the workflow stage. */
    public function progressPercentage(): int
    {
        if ($this->code()?->isTerminal()) {
            return 100;
        }

        $maxStage = (int) collect(config('portal.workflow'))->max(
            fn (array $edge) => (int) ($edge['stage'] ?? 1)
        );

        $maxStage = max($maxStage - 1, 1);

        return (int) round(min(1, max(0, ($this->stage - 1) / $maxStage)) * 100);
    }
}
