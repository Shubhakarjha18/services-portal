<?php

declare(strict_types=1);

namespace App\Support\Presenters;

use App\Models\Application;
use App\Models\ApplicationComment;
use App\Models\ApplicationDocument;
use App\Models\ApplicationTransition;
use App\Models\User;

/**
 * Turns models into the flat prop shapes the Vue pages expect. Kept separate
 * from the API resources: the Inertia payload also carries UI hints such as the
 * workflow buttons a specific user may press.
 */
class ApplicationPresenter
{
    /** @return array<string, mixed> */
    public static function card(Application $application): array
    {
        return [
            'id' => $application->id,
            'reference' => $application->reference_number,
            'title' => $application->title,
            'service' => $application->serviceType?->name,
            'department' => $application->department?->name,
            'officer' => $application->officer?->name,
            'applicant' => $application->applicant_name,
            'status' => [
                'code' => $application->code()?->value,
                'name' => $application->status?->name,
                'color' => $application->status?->color,
            ],
            'priority' => $application->priority->value,
            'progress' => $application->progressPercentage(),
            'submitted_at' => $application->submitted_at?->toDateString(),
            'due_at' => $application->due_at?->toDateString(),
            'days_remaining' => $application->slaDaysRemaining(),
            'is_overdue' => $application->isOverdue(),
            'documents' => $application->documents_count ?? $application->documents()->count(),
            'updated_at' => $application->last_activity_at?->diffForHumans(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(Application $application, User $actor, array $transitions = []): array
    {
        $isStaff = $actor->isStaff();

        return [
            ...self::card($application),
            'description' => $application->description,
            'applicant_phone' => $application->applicant_phone,
            'applicant_address' => $application->applicant_address,
            'citizen' => $application->citizen?->only(['id', 'name', 'email', 'national_id']),
            'service_fee' => $application->serviceType ? (float) $application->serviceType->fee : null,
            'required_documents' => $application->serviceType?->requiredDocumentLabels() ?? [],
            'stage' => $application->stage,
            'closed_at' => $application->closed_at?->toIso8601String(),
            'created_at' => $application->created_at?->toIso8601String(),
            'documents' => $application->documents
                ->filter(fn (ApplicationDocument $document) => $isStaff || $document->uploaded_by === $actor->id)
                ->map(fn (ApplicationDocument $document) => self::document($document, $actor))
                ->values()
                ->all(),
            'comments' => $application->comments
                ->filter(fn (ApplicationComment $comment) => ! $comment->is_internal || $isStaff)
                ->sortByDesc('id')
                ->map(fn (ApplicationComment $comment) => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'is_internal' => $comment->is_internal,
                    'author' => $comment->author?->name,
                    'role' => $comment->author?->roleName()?->value,
                    'created_at' => $comment->created_at?->diffForHumans(),
                ])
                ->values()
                ->all(),
            'timeline' => $transitions !== [] ? array_map(self::timeline(...), $transitions) : null,
            'actions' => $application->getAttribute('available_transitions') ?? [],
            'can' => [
                'comment' => $actor->can('comment', $application),
                'upload' => $actor->can('uploadDocument', $application),
                'assign' => $actor->can('assign', Application::class),
                'withdraw' => $actor->can('delete', $application),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function document(ApplicationDocument $document, ?User $actor = null): array
    {
        return [
            'id' => $document->id,
            'label' => $document->label,
            'name' => $document->original_name,
            'size_kb' => (int) round($document->size_bytes / 1024),
            'status' => $document->status->value,
            'status_label' => $document->status->label(),
            'review_note' => $actor?->isStaff() ? $document->review_note : null,
            'uploaded_by' => $document->uploader?->name,
            'checksum' => substr($document->checksum, 0, 12),
            'download_url' => route('portal.documents.download', $document),
            'created_at' => $document->created_at?->diffForHumans(),
        ];
    }

    /**
     * @param  ApplicationTransition  $transition
     * @return array<string, mixed>
     */
    public static function timeline($transition): array
    {
        return [
            'id' => $transition->id,
            'status' => $transition->toStatus?->code->value,
            'status_name' => $transition->toStatus?->name,
            'color' => $transition->toStatus?->color,
            'note' => $transition->note,
            'actor' => $transition->performed_by_name,
            'at' => $transition->created_at?->toDateTimeString(),
            'human' => $transition->created_at?->diffForHumans(),
            'internal' => ! $transition->visible_to_citizen,
        ];
    }
}
