<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationStatusCode: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case DocumentsRequired = 'documents_required';
    case InProgress = 'in_progress';
    case OnHold = 'on_hold';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under review',
            self::DocumentsRequired => 'Additional documents required',
            self::InProgress => 'Processing',
            self::OnHold => 'On hold',
            self::PendingApproval => 'Pending approval',
            self::Approved => 'Approved',
            self::Completed => 'Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }

    /**
     * Transitions declared for this status in config/portal.php.
     *
     * @return array<string, array<int, RoleName>>
     */
    public function allowedTransitions(): array
    {
        $edges = config("portal.workflow.{$this->value}.to", []);

        return is_array($edges) ? $edges : [];
    }

    /**
     * @return array<int, string>
     */
    public function allowedTargetCodes(): array
    {
        return array_keys($this->allowedTransitions());
    }

    public function stage(): int
    {
        return (int) config("portal.workflow.{$this->value}.stage", 1);
    }
}
