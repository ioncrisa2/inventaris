<?php

namespace App\Http\Controllers;

use App\Http\Requests\Owner\OwnerAnalyticsRequest;
use App\Services\OwnerAnalyticsService;
use Illuminate\Contracts\View\View;

class SystemOwnerDashboardController extends Controller
{
    public function __construct(private OwnerAnalyticsService $analyticsService) {}

    public function __invoke(OwnerAnalyticsRequest $request): View
    {
        $filters = $request->filters();
        $analytics = $this->analyticsService->dashboard($filters);

        return view('owner.dashboard', [
            'analytics' => $analytics,
            'filters' => $filters,
        ]);
    }
}
