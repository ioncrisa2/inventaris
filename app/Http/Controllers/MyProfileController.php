<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class MyProfileController extends Controller
{
    public function __invoke(Request $request): View
    {
        $karyawan = $request->user()->karyawan()
            ->with(['unitKerja:id,nama_unit', 'atasanLangsung:id,nama_lengkap'])
            ->first();

        return view('me.profile', compact('karyawan'));
    }
}
