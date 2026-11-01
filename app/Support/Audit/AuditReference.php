<?php

declare(strict_types=1);

namespace App\Support\Audit;

/**
 * Implemented by models the compliance trail should be searchable by case
 * reference. Child records (documents, comments) resolve the reference of the
 * application they belong to so one case shows every related action.
 */
interface AuditReference
{
    public function auditReference(): ?string;
}
