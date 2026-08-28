<?php

namespace App\Http\Controllers;

use App\Models\PlatformAnnouncement;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function show(Request $request, PlatformAnnouncement $announcement): View
    {
        $user = $request->user();
        $isTarget = $user->isAdminPrimer()
            && ($announcement->target_koperasi_id === null
                || (int) $announcement->target_koperasi_id === (int) $user->koperasi_id);

        abort_unless($isTarget && $announcement->isVisibleNow(), 404);

        return view('announcements.show', compact('announcement'));
    }
}
