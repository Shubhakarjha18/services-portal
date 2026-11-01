<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ApplicationDocument;
use App\Models\User;

/**
 * Uploaded paperwork is only readable by the person who owns the case it was
 * submitted against, and only verifiable by the officer who holds that case.
 */
class ApplicationDocumentPolicy
{
    public function view(User $actor, ApplicationDocument $document): bool
    {
        return $this->holds($actor, $document) || $actor->hasRole(RoleName::Admin);
    }

    public function verifyDocument(User $actor, ApplicationDocument $document): bool
    {
        if ($actor->hasRole(RoleName::Admin)) {
            return true;
        }

        return $actor->hasRole(RoleName::Officer)
            && $actor->hasPermission('documents.verify')
            && $this->holds($actor, $document);
    }

    public function download(User $actor, ApplicationDocument $document): bool
    {
        return $this->view($actor, $document);
    }

    public function deleteDocument(User $actor, ApplicationDocument $document): bool
    {
        if ($actor->hasRole(RoleName::Admin)) {
            return true;
        }

        // A citizen may pull back their own upload while it is still unverified.
        return $document->uploaded_by === $actor->id
            && $document->application?->citizen_id === $actor->id
            && ! $document->isVerified();
    }

    private function holds(User $actor, ApplicationDocument $document): bool
    {
        $application = $document->application;

        if ($application === null) {
            return false;
        }

        if ($actor->hasRole(RoleName::Officer)) {
            return $application->assigned_officer_id === $actor->id
                || $application->citizen_id === $actor->id;
        }

        return $application->citizen_id === $actor->id;
    }
}
