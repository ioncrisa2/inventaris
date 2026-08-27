<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PanduanSingkatController extends Controller
{
    public function show(Request $request): View
    {
        return view('panduan.show', [
            'guide' => $this->guideFor($request->user()),
        ]);
    }

    public function print(Request $request): View
    {
        return view('panduan.print', [
            'guide' => $this->guideFor($request->user()),
        ]);
    }

    /**
     * Panduan dipilih dari identitas role sistem, bukan permission biasa,
     * supaya role custom tidak menerima instruksi untuk kewenangan yang
     * sebenarnya tidak mereka miliki.
     *
     * @return array<string, mixed>
     */
    private function guideFor(User $user): array
    {
        if ($user->isSuperAdmin()) {
            return [
                'key' => 'super-admin',
                'title' => 'Panduan Super Admin',
                'subtitle' => 'Kelola koperasi anggota, masa aktif, akses, dan pengawasan lintas koperasi.',
                'audience' => 'Super Admin PHS Sumsel',
                'scope' => 'Seluruh koperasi',
                'content_view' => 'panduan.content.super-admin',
                'actions' => [
                    ['label' => 'Kelola Koperasi', 'route' => 'koperasi.index', 'icon' => 'bi-buildings'],
                    ['label' => 'Kelola Role', 'route' => 'role.index', 'icon' => 'bi-shield-lock'],
                ],
            ];
        }

        if ($user->isAdminPrimer()) {
            return [
                'key' => 'admin-primer',
                'title' => 'Panduan Admin Primer',
                'subtitle' => 'Siapkan dan jalankan operasional koperasi Anda dari satu alur yang terarah.',
                'audience' => 'Admin Primer '.$user->koperasi->nama,
                'scope' => $user->koperasi->nama,
                'content_view' => 'panduan.content.admin-primer',
                'actions' => [
                    ['label' => 'Buka Pengaturan', 'route' => 'pengaturan.edit', 'icon' => 'bi-sliders'],
                    ['label' => 'Atur Unit Kerja', 'route' => 'unit-kerja.index', 'icon' => 'bi-diagram-3'],
                ],
            ];
        }

        abort(403, 'Panduan ini hanya tersedia untuk pengelola sistem.');
    }
}
