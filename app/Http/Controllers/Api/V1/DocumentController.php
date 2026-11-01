<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewDocumentRequest;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Services\ApplicationService;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * POST /api/v1/applications/{application}/documents (multipart)
     */
    public function store(StoreDocumentRequest $request, Application $application): JsonResponse
    {
        $document = $this->applications->attachDocument(
            application: $application,
            uploader: $request->user(),
            file: $request->file('file'),
            label: $request->documentLabel(),
            note: $request->input('comment'),
        );

        return (new DocumentResource($document->load('uploader:id,name')))
            ->additional(['message' => 'Document received and queued for verification.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/documents/{document}/download
     *
     * Files are stored on a private disk with no public URL, so this route is
     * the only way to read one — and every read is audited.
     */
    public function download(Request $request, ApplicationDocument $document): StreamedResponse
    {
        Gate::authorize('download', $document);

        $this->audit->log(
            event: 'document_downloaded',
            subject: $document,
            meta: ['checksum' => $document->checksum],
            actor: $request->user(),
        );

        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404, 'The stored file is missing.');

        return $disk->download($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ]);
    }

    /**
     * PATCH /api/v1/documents/{document}/review — officer verdict on paperwork.
     */
    public function review(ReviewDocumentRequest $request, ApplicationDocument $document): DocumentResource
    {
        $reviewed = $this->applications->reviewDocument(
            $document,
            $request->validated(),
            $request->user()
        );

        return new DocumentResource($reviewed);
    }

    /**
     * DELETE /api/v1/documents/{document}
     */
    public function destroy(Request $request, ApplicationDocument $document): JsonResponse
    {
        Gate::authorize('deleteDocument', $document);

        $this->applications->removeDocument($document, $request->user());

        return response()->json(['message' => 'Document removed.']);
    }
}
