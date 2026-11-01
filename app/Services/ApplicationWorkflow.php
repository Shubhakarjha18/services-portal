<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatusCode;
use App\Enums\RoleName;
use App\Exceptions\WorkflowException;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\ApplicationTransition;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * The single place that is allowed to change an application's status.
 *
 * Rules come from config/portal.php, so the workflow is data-driven and can be
 * extended without touching controllers.
 */
class ApplicationWorkflow
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Statuses the actor may move this application to right now.
     *
     * @return array<int, array{code: string, name: string, color: string}>
     */
    public function availableTransitions(Application $application, User $actor): array
    {
        $from = $application->code();

        if ($from === null) {
            return [];
        }

        return collect($from->allowedTransitions())
            ->filter(fn (array $roles, string $code) => $this->roleAllows($actor, $roles, $code)
                && $actor->can('transition', [$application, $code]))
            ->map(fn (array $roles, string $code) => [
                'code' => $code,
                'name' => ApplicationStatusCode::tryFrom($code)?->label() ?? $code,
                'color' => $this->colorFor($code),
            ])
            ->values()
            ->all();
    }

    public function canTransition(Application $application, User $actor, string $toCode): bool
    {
        $from = $application->code();

        if ($from === null) {
            return false;
        }

        $roles = $from->allowedTransitions()[$toCode] ?? null;

        if ($roles === null || ! $this->roleAllows($actor, $roles, $toCode)) {
            return false;
        }

        return $actor->can('transition', [$application, $toCode]);
    }

    /**
     * @throws WorkflowException
     */
    public function transition(
        Application $application,
        string $toCode,
        ?string $note = null,
        ?User $actor = null,
        bool $visibleToCitizen = true,
    ): ApplicationTransition {
        $actor ??= auth()->user();
        $from = $application->code();
        $to = ApplicationStatusCode::tryFrom($toCode);

        if (! $actor instanceof User) {
            throw new WorkflowException('An authenticated actor is required to change a status.');
        }

        if ($to === null) {
            throw new WorkflowException("Unknown status [{$toCode}].");
        }

        if ($from === $to) {
            throw new WorkflowException("The application is already {$to->label()}.");
        }

        if (! $this->canTransition($application, $actor, $to->value)) {
            throw new WorkflowException(
                "Transition {$from?->value} -> {$to->value} is not permitted for role ".($actor->roleName()?->value ?? 'none').'.'
            );
        }

        $toStatus = ApplicationStatus::where('code', $to->value)->firstOrFail();

        return DB::transaction(function () use ($application, $from, $to, $toStatus, $note, $actor, $visibleToCitizen) {
            $previousTransition = $application->transitions()
                ->latest('id')
                ->first();

            $duration = $previousTransition
                ? max(0, $previousTransition->created_at->diffInSeconds(now()))
                : null;

            $transition = $application->transitions()->create([
                'from_status_id' => $application->application_status_id,
                'to_status_id' => $toStatus->id,
                'performed_by' => $actor->id,
                'performed_by_name' => $actor->name,
                'note' => $note,
                'stage' => $to->stage(),
                'visible_to_citizen' => $visibleToCitizen,
                'duration_seconds' => $duration,
            ]);

            $before = [
                'status' => $from?->value,
                'stage' => $application->stage,
                'closed_at' => $application->closed_at?->toIso8601String(),
                'assigned_officer_id' => $application->assigned_officer_id,
            ];

            $application->application_status_id = $toStatus->id;
            $application->setRelation('status', $toStatus);
            $application->stage = $to->stage();
            $application->last_activity_at = now();
            $application->closed_at = $to->isTerminal() ? now() : null;

            // First officer to touch an unassigned case claims it.
            if ($application->assigned_officer_id === null && $actor->hasRole(RoleName::Officer)) {
                $application->assigned_officer_id = $actor->id;
            }

            $application->save();

            $after = [
                'status' => $to->value,
                'stage' => $application->stage,
                'closed_at' => $application->closed_at?->toIso8601String(),
                'assigned_officer_id' => $application->assigned_officer_id,
            ];

            $this->audit->log(
                event: 'status_changed',
                subject: $application,
                old: $before,
                new: $after,
                meta: [
                    'note' => $note,
                    'transition_id' => $transition->id,
                    'duration_seconds' => $duration,
                ],
                actor: $actor,
            );

            return $transition;
        });
    }

    /** Open the timeline for a newly submitted application. */
    public function recordSubmission(Application $application, User $citizen): ApplicationTransition
    {
        $status = ApplicationStatus::where('code', ApplicationStatusCode::Submitted->value)->firstOrFail();

        $transition = $application->transitions()->create([
            'from_status_id' => null,
            'to_status_id' => $status->id,
            'performed_by' => $citizen->id,
            'performed_by_name' => $citizen->name,
            'note' => 'Application submitted by the applicant.',
            'stage' => ApplicationStatusCode::Submitted->stage(),
            'visible_to_citizen' => true,
        ]);

        $this->audit->log(
            event: 'submitted',
            subject: $application,
            new: ['status' => ApplicationStatusCode::Submitted->value],
            actor: $citizen,
        );

        return $transition;
    }

    /**
     * @param  array<int, RoleName>  $roles
     */
    private function roleAllows(User $actor, array $roles, string $toCode): bool
    {
        $matchesRole = in_array($actor->roleName(), $roles, true);

        if (! $matchesRole) {
            return false;
        }

        $requiredPermission = $this->permissionFor($toCode);

        return $requiredPermission === null || $actor->hasPermission($requiredPermission);
    }

    private function permissionFor(string $toCode): ?string
    {
        return match ($toCode) {
            ApplicationStatusCode::Approved->value,
            ApplicationStatusCode::Rejected->value => 'applications.decide',
            default => null,
        };
    }

    private function colorFor(string $code): string
    {
        return ApplicationStatusCode::tryFrom($code) === null
            ? 'gray'
            : (ApplicationStatus::where('code', $code)->value('color') ?? 'gray');
    }
}
