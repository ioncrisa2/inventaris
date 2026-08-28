<?php

namespace App\Http\Controllers;

use App\Services\StorageUsageService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorageUsageController extends Controller
{
    public function __construct(private StorageUsageService $storageUsageService) {}

    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->isSystemOwner() === true, 403);

        return view('owner.storage', [
            'storage' => $this->storageUsageService->snapshot(),
        ]);
    }
}
