<?php

namespace App\Http\Controllers;

use App\Enums\ProductRequestPriority;
use App\Enums\ProductRequestStatus;
use App\Enums\ProductRequestType;
use App\Http\Requests\ProductRequest\ChangeProductRequestState;
use App\Http\Requests\ProductRequest\ProductRequestIndexRequest;
use App\Http\Requests\ProductRequest\StoreProductRequest;
use App\Models\ProductRequest;
use App\Repositories\ProductRequestRepository;
use App\Services\ProductRequestService;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductRequestController extends Controller
{
    public function __construct(
        private ProductRequestRepository $repository,
        private ProductRequestService $service,
    ) {}

    public function index(ProductRequestIndexRequest $request): View
    {
        return view('product-requests.index', [
            'productRequests' => $this->repository->paginateFor(
                $request->user(),
                $request->validated(),
                PerPage::resolve($request),
            ),
            'types' => ProductRequestType::options(),
            'statuses' => ProductRequestStatus::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProductRequest::class);

        return view('product-requests.create', [
            'types' => ProductRequestType::options(),
            'priorities' => ProductRequestPriority::options(),
            'modules' => config('product_requests.modules'),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $productRequest = $this->service->create($request->user(), $request->validated());

        return redirect()
            ->route('product-requests.show', $productRequest->ticket_number)
            ->with('success', "Request {$productRequest->ticket_number} berhasil diajukan.");
    }

    public function show(Request $request, string $productRequest): View
    {
        $record = $this->repository->findFor($request->user(), $productRequest);
        $this->authorize('view', $record);

        return view('product-requests.show', [
            'productRequest' => $record,
            'canReply' => $request->user()->can('reply', $record),
            'canClose' => $request->user()->can('close', $record),
        ]);
    }

    public function toggle(
        ChangeProductRequestState $request,
        string $productRequest,
    ): RedirectResponse {
        $record = $this->repository->findFor($request->user(), $productRequest);
        $wasClosed = $record->status === ProductRequestStatus::Closed;
        $this->service->toggleClosed($request->user(), $record, $request->validated());

        return redirect()
            ->route('product-requests.show', $record->ticket_number)
            ->with('success', $wasClosed ? 'Request dibuka kembali.' : 'Request ditutup.');
    }
}
