<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    public function enter(User $user, Request $request): RedirectResponse
    {
        $currentUser = $request->user();

        // Hanya System Owner asli yang boleh melakukan impersonasi
        if (! $currentUser->isSystemOwner()) {
            abort(403, 'Akses ditolak. Fitur khusus System Owner.');
        }

        // Tidak bisa impersonate diri sendiri atau akun platform lain
        if ($user->id === $currentUser->id || $user->isPlatformAccount()) {
            abort(403, 'Tidak dapat meng-impersonasi akun ini.');
        }

        // Catat original_user_id di session
        $request->session()->put('impersonated_by', $currentUser->id);

        Auth::login($user);

        return redirect()->route('dashboard')->with('success', "Menyamar sebagai {$user->name}.");
    }

    public function leave(Request $request): RedirectResponse
    {
        if (! $request->session()->has('impersonated_by')) {
            abort(400, 'Tidak sedang dalam mode impersonasi.');
        }

        $originalUserId = $request->session()->pull('impersonated_by');
        $originalUser = User::find($originalUserId);

        if (! $originalUser || ! $originalUser->isSystemOwner()) {
            Auth::logout();
            return redirect()->route('login')->withErrors(['Sesi impersonasi tidak valid.']);
        }

        Auth::login($originalUser);

        return redirect()->route('owner.userlist.index')->with('success', 'Kembali ke sesi System Owner.');
    }
}
