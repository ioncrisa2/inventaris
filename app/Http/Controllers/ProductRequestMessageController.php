<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest\StoreProductRequestMessage;
use App\Repositories\ProductRequestRepository;
use App\Services\ProductRequestMessageService;
use Illuminate\Http\RedirectResponse;

class ProductRequestMessageController extends Controller
{
    public function __construct(
        private ProductRequestRepository $repository,
        private ProductRequestMessageService $service,
    ) {}

    public function store(StoreProductRequestMessage $request, string $productRequest): RedirectResponse
    {
        $record = $this->repository->findFor($request->user(), $productRequest);
        $this->authorize('reply', $record);
        $this->service->tenantReply($request->user(), $record, $request->validated());

        return redirect()
            ->route('product-requests.show', $record->ticket_number)
            ->with('success', 'Balasan berhasil dikirim.');
    }
}
