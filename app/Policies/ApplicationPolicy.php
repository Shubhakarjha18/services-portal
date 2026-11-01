<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ApplicationStatusCode;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\User;

/**
 * Row-level visibility and action rights for citizen applications.
 *
 * Role rules:
 *  - admin   -> everything
 *  - officer -> own cases + unassigned cases of their department
 *  - public  -> only their own submissions, and only non-destructive actions
 */
class ApplicationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active;
    }

    public function view(User $actor, Application $application): bool
    {
        return $this->canSee($actor, $application);
    }

    public function create(User $actor): bool
    {
        return $actor->is_active;
    }

    public function update(User $actor, Application $application): bool
    {
        if ($actor->hasRole(RoleName::Admin)) {
            return true;
        }

        if ($actor->hasRole(RoleName::Officer)) {
            return $this->ownsCase($actor, $application);
        }

        // Citizens may edit a submission only while it has not been reviewed.
        return $application->citizen_id === $actor->id
            && in_array($application->code(), [ApplicationStatusCode::Submitted], true);
    }

    public function delete(User $actor, Application $application): bool
    {
        if ($actor->hasRole(RoleName::Admin)) {
            return true;
        }

        return $application->citizen_id === $actor->id
            && in_array($application->code(), [
                ApplicationStatusCode::Submitted,
                ApplicationStatusCode::Cancelled,
            ], true);
    }

    public function assign(User $actor): bool
    {
        return $actor->hasPermission('applications.assign');
    }

    public function decide(User $actor): bool
    {
        return $actor->hasPermission('applications.decide');
    }

    public function comment(User $actor, Application $application): bool
    {
        return $actor->is_active && $this->canSee($actor, $application);
    }

    public function uploadDocument(User $actor, Application $application): bool
    {
        if ($application->code()?->isTerminal()) {
            return false;
        }

        return $actor->hasRole(RoleName::Admin)
            || $this->ownsCase($actor, $application)
            || $application->citizen_id === $actor->id;
    }

    /**
     * Second argument is the target status code, so approval-only abilities can
     * be checked before the workflow engine runs.
     */
    public function transition(User $actor, Application $application, ?string $toCode = null): bool
    {
        if (! $actor->is_active) {
            return false;
        }

        if ($actor->hasRole(RoleName::Admin)) {
            return true;
        }

        if ($actor->hasRole(RoleName::Public)) {
            return $application->citizen_id === $actor->id;
        }

        // Officers may only drive cases they hold or can claim from their queue.
        return $this->ownsCase($actor, $application)
            || $this->canClaim($actor, $application);
    }

    public function before(User $actor, string $ability): ?bool
    {
        return $actor->hasRole(RoleName::Admin) ? true : null;
    }

    private function canSee(User $actor, Application $application): bool
    {
        return match (true) {
            $actor->hasRole(RoleName::Admin) => true,
            $actor->hasRole(RoleName::Officer) => $this->ownsCase($actor, $application)
                || $this->canClaim($actor, $application)
                || $application->department_id === $actor->department_id,
            default => $application->citizen_id === $actor->id,
        };
    }

    private function ownsCase(User $actor, Application $application): bool
    {
        return $application->assigned_officer_id === $actor->id;
    }

    private function canClaim(User $actor, Application $application): bool
    {
        return $application->assigned_officer_id === null
            && $application->department_id === $actor->department_id;
    }
}
