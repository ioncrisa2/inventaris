<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function __construct(private SystemHealthService $systemHealthService) {}

    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isSystemOwner() === true, 403);

        return view('owner.system-health', [
            'health' => $this->systemHealthService->snapshot(),
        ]);
    }
}
