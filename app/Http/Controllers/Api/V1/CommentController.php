<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Application;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * POST /api/v1/applications/{application}/comments
     *
     * Staff messages can be flagged internal; the flag is dropped for citizens.
     */
    public function store(StoreCommentRequest $request, Application $application): JsonResponse
    {
        $comment = $application->comments()->create([
            'user_id' => $request->user()->id,
            'body' => trim((string) $request->input('body')),
            'is_internal' => $request->isInternal(),
        ]);

        $application->forceFill(['last_activity_at' => now()])->save();

        $this->audit->log(
            event: 'comment_added',
            subject: $application,
            new: ['comment_id' => $comment->id, 'is_internal' => $comment->is_internal],
            actor: $request->user(),
        );

        return (new CommentResource($comment->load('author:id,name,role_id')))
            ->additional(['message' => 'Message added to the case.'])
            ->response()
            ->setStatusCode(201);
    }
}
