<?php

namespace App\Http\Controllers;

use App\Enums\ProductRequestPriority;
use App\Enums\ProductRequestStatus;
use App\Enums\ProductRequestType;
use App\Http\Requests\ProductRequest\OwnerProductRequestIndexRequest;
use App\Http\Requests\ProductRequest\UpdateOwnerProductRequestTriage;
use App\Repositories\OwnerProductRequestRepository;
use App\Services\ProductRequestTransitionService;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OwnerProductRequestController extends Controller
{
    public function __construct(
        private OwnerProductRequestRepository $repository,
        private ProductRequestTransitionService $transitionService,
    ) {}

    public function index(OwnerProductRequestIndexRequest $request): View
    {
        $filters = $request->validated();

        return view('owner.product-requests.index', [
            'productRequests' => $this->repository->paginate($filters, PerPage::resolve($request)),
            'statistics' => $this->repository->statistics($filters),
            'koperasis' => $this->repository->koperasis(),
            'owners' => $this->repository->owners(),
            'types' => ProductRequestType::options(),
            'statuses' => ProductRequestStatus::options(),
            'priorities' => ProductRequestPriority::options(),
        ]);
    }

    public function show(Request $request, string $productRequest): View
    {
        return view('owner.product-requests.show', [
            'productRequest' => $this->repository->find($productRequest),
            'owners' => $this->repository->owners(),
            'statuses' => ProductRequestStatus::options(),
            'priorities' => ProductRequestPriority::options(),
        ]);
    }

    public function update(
        UpdateOwnerProductRequestTriage $request,
        string $productRequest,
    ): RedirectResponse {
        $record = $this->repository->find($productRequest);
        $this->transitionService->updateOwnerTriage($request->user(), $record, $request->validated());

        return redirect()
            ->route('owner.product-requests.show', $record->ticket_number)
            ->with('success', 'Triase request berhasil diperbarui.');
    }
}
