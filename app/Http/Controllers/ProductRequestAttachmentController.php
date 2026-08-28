<?php

namespace App\Http\Controllers;

use App\Enums\ProductRequestMessageVisibility;
use App\Repositories\ProductRequestRepository;
use App\Services\ProductRequestAttachmentService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductRequestAttachmentController extends Controller
{
    public function __construct(
        private ProductRequestRepository $repository,
        private ProductRequestAttachmentService $service,
    ) {}

    public function __invoke(
        Request $request,
        string $productRequest,
        int $attachment,
    ): StreamedResponse {
        $record = $this->repository->findFor($request->user(), $productRequest);
        $this->authorize('downloadAttachment', $record);
        $file = $this->repository->findAttachment($record, $attachment);

        abort_if(
            $file->message !== null
                && $file->message->visibility !== ProductRequestMessageVisibility::Public,
            404,
        );

        return $this->service->download($file);
    }
}
