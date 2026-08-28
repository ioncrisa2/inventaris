<?php

namespace App\Http\Controllers;

use App\Repositories\OwnerProductRequestRepository;
use App\Services\ProductRequestAttachmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerProductRequestAttachmentController extends Controller
{
    public function __construct(
        private OwnerProductRequestRepository $repository,
        private ProductRequestAttachmentService $service,
    ) {}

    public function __invoke(
        Request $request,
        string $productRequest,
        int $attachment,
    ): StreamedResponse {
        abort_unless($request->user()->isSystemOwner(), 403);
        $record = $this->repository->find($productRequest);
        $file = $this->repository->findAttachment($record, $attachment);

        return $this->service->download($file);
    }
}
