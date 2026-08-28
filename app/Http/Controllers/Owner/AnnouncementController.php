<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\StoreAnnouncementRequest;
use App\Models\Koperasi;
use App\Models\PlatformAnnouncement;
use App\Services\PlatformAnnouncementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(private PlatformAnnouncementService $announcementService) {}

    public function index(): View
    {
        return view('owner.announcements.index', [
            'announcements' => PlatformAnnouncement::query()->latest()->paginate(20),
            'koperasis' => Koperasi::query()->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(StoreAnnouncementRequest $request): RedirectResponse
    {
        $this->announcementService->create($request->user(), $request->validated());

        return back()->with('success', 'Draf pengumuman berhasil dibuat.');
    }

    public function publish(PlatformAnnouncement $announcement): RedirectResponse
    {
        try {
            $this->announcementService->publish($announcement);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pengumuman berhasil diterbitkan kepada Admin Primer yang ditargetkan.');
    }
}
