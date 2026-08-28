<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest\StoreOwnerProductRequestMessage;
use App\Repositories\OwnerProductRequestRepository;
use App\Services\ProductRequestMessageService;
use Illuminate\Http\RedirectResponse;

class OwnerProductRequestMessageController extends Controller
{
    public function __construct(
        private OwnerProductRequestRepository $repository,
        private ProductRequestMessageService $service,
    ) {}

    public function store(StoreOwnerProductRequestMessage $request, string $productRequest): RedirectResponse
    {
        $record = $this->repository->find($productRequest);
        $this->service->ownerMessage($request->user(), $record, $request->validated());

        return redirect()
            ->route('owner.product-requests.show', $record->ticket_number)
            ->with('success', $request->validated('visibility') === 'internal'
                ? 'Catatan internal berhasil disimpan.'
                : 'Balasan publik berhasil dikirim.');
    }
}
