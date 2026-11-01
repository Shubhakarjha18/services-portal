<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a status change violates the declared workflow or the actor's
 * role. Mapped to HTTP 422 in the exception handler.
 */
class WorkflowException extends Exception {}
