<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackApplicationRequest;
use App\Models\Application;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * The anonymous "where is my document?" endpoint.
 *
 * It returns the minimum needed to answer that question: a reference number is
 * not a secret, so contact details stay masked unless the caller also proves
 * ownership with the citizen number. Access is rate limited and logged.
 */
class PublicTrackingController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function track(TrackApplicationRequest $request): JsonResponse
    {
        $application = Application::withTrashed()
            ->with(['status', 'department:id,name', 'serviceType.department', 'citizen:id,national_id', 'transitions.toStatus', 'transitions.fromStatus'])
            ->where('reference_number', $request->reference())
            ->first();

        if ($application === null) {
            $this->audit->log(
                event: 'tracking_miss',
                meta: ['reference' => $request->reference()],
            );

            return response()->json([
                'message' => 'No application matches that tracking number.',
                'code' => 'not_found',
            ], 404);
        }

        $ownerVerified = $request->nationalId() !== null
            && $application->citizen?->national_id === $request->nationalId();

        if ($application->trashed()) {
            return response()->json([
                'message' => 'This application was withdrawn by the applicant.',
                'data' => ['reference_number' => $application->reference_number, 'state' => 'withdrawn'],
            ]);
        }

        $timeline = $application->transitions
            ->where('visible_to_citizen', true)
            ->sortBy('id')
            ->map(fn ($transition) => [
                'status' => $transition->toStatus?->code->value,
                'status_name' => $transition->toStatus?->name,
                'color' => $transition->toStatus?->color,
                'note' => $transition->note,
                'actor' => $this->maskActor($transition->performed_by_name),
                'timestamp' => $transition->created_at?->toIso8601String(),
            ])
            ->values();

        $this->audit->log(
            event: 'tracked',
            subject: $application,
            meta: ['owner_verified' => $ownerVerified, 'ip' => $request->ip()],
        );

        return response()->json([
            'data' => [
                'reference_number' => $application->reference_number,
                'service' => $application->serviceType?->name,
                'department' => $application->department?->name,
                'title' => $application->title,
                'status' => [
                    'code' => $application->code()?->value,
                    'name' => $application->status?->name,
                    'color' => $application->status?->color,
                ],
                'progress' => $application->progressPercentage(),
                'stage' => $application->stage,
                'submitted_at' => $application->submitted_at?->toIso8601String(),
                'due_at' => $application->due_at?->toIso8601String(),
                'is_overdue' => $application->isOverdue(),
                'last_activity_at' => $application->last_activity_at?->toIso8601String(),
                'applicant_name' => $ownerVerified
                    ? $application->applicant_name
                    : $this->maskName($application->applicant_name),
            ],
            'meta' => [
                'owner_verified' => $ownerVerified,
                'timeline' => $timeline,
                'poll_after_seconds' => (int) config('portal.tracking_poll_seconds'),
            ],
        ]);
    }

    private function maskName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return collect($parts)
            ->map(fn (string $part, int $index) => $index === 0 || strlen($part) < 2
                ? $part
                : substr($part, 0, 1).'***')
            ->implode(' ');
    }

    private function maskActor(?string $name): string
    {
        if ($name === null) {
            return 'Portal system';
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return ($parts[0] ?? 'Staff').' '.$this->maskName(implode(' ', array_slice($parts, 1)));
    }
}
