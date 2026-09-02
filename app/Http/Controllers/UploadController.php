<?php

namespace App\Http\Controllers;

use App\Http\Requests\Upload\StoreUploadRequest;
use App\Models\StoredFile;
use App\Services\AsyncUploadService;
use App\Services\StoredFileAccessService;
use App\Services\StoredFileAuditService;
use App\Services\StoredFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UploadController extends Controller
{
    public function __construct(
        private AsyncUploadService $uploads,
        private StoredFileAccessService $access,
        private StoredFileService $storedFiles,
        private StoredFileAuditService $audit,
    ) {}

    public function store(StoreUploadRequest $request): JsonResponse
    {
        $this->ensureEnabled();
        $file = $this->uploads->stage(
            $request->file('file'),
            $request->string('policy')->value(),
            $request->user(),
            $request,
            $request->validated('koperasi_id'),
        );

        return response()->json($this->uploads->statusPayload($file), 202);
    }

    public function show(Request $request, StoredFile $storedFile): JsonResponse
    {
        $this->ensureEnabled();
        abort_unless($this->access->canAccess($request->user(), $storedFile), 403);

        return response()->json($this->uploads->statusPayload($storedFile->refresh()));
    }

    public function destroy(Request $request, StoredFile $storedFile): Response
    {
        $this->ensureEnabled();
        $this->uploads->cancel($storedFile, $request->user(), $request);

        return response()->noContent();
    }

    public function preview(Request $request, StoredFile $storedFile): Response
    {
        $this->ensureEnabled();
        abort_unless($this->access->canAccess($request->user(), $storedFile), 403);
        $response = $this->storedFiles->storedFileResponse($storedFile, true);
        $this->audit->record($storedFile, 'preview', $request->user(), $request);

        return $response;
    }

    public function download(Request $request, StoredFile $storedFile): Response
    {
        $this->ensureEnabled();
        abort_unless($this->access->canAccess($request->user(), $storedFile), 403);
        $response = $this->storedFiles->storedFileResponse($storedFile, false);
        $this->audit->record($storedFile, 'download', $request->user(), $request);

        return $response;
    }

    private function ensureEnabled(): void
    {
        abort_unless((bool) config('uploads.features.async', false), 404);
    }
}
