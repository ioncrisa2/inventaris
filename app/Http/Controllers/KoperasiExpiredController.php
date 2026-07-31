<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class KoperasiExpiredController extends Controller
{
    /**
     * Halaman info saat koperasi user sudah nonaktif/lewat masa aktif.
     * User yang koperasinya masih aktif tapi nyasar ke sini (mis. buka
     * bookmark lama) langsung dialihkan balik ke dashboard.
     */
    public function __invoke(Request $request)
    {
        $koperasi = $request->user()->koperasi;

        if ($koperasi && $koperasi->is_active && (! $koperasi->expires_at || ! $koperasi->expires_at->isPast())) {
            return redirect()->route('dashboard');
        }

        return view('koperasi.expired', compact('koperasi'));
    }
}
