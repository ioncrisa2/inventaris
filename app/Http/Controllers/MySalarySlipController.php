<?php

namespace App\Http\Controllers;

use App\Models\SalaryAccessLog;
use App\Models\TransaksiGaji;
use App\Support\PerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MySalarySlipController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $karyawan = $request->user()->karyawan()->first();

        if (! $karyawan) {
            return redirect()->route('me.profile')
                ->with('error', 'Akun Anda belum terhubung dengan data karyawan.');
        }

        $slips = TransaksiGaji::query()
            ->where('karyawan_id', $karyawan->id)
            ->whereNotNull('published_at')
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->paginate(PerPage::resolve($request));

        return view('me.salary-slips.index', compact('karyawan', 'slips'));
    }

    public function show(Request $request, TransaksiGaji $transaksiGaji): View
    {
        $karyawan = $request->user()->karyawan()->firstOrFail();

        abort_unless(
            $transaksiGaji->published_at !== null
                && (int) $transaksiGaji->karyawan_id === (int) $karyawan->id,
            404,
        );

        $transaksiGaji->load('details');
        SalaryAccessLog::query()->create([
            'actor_user_id' => $request->user()->id,
            'transaksi_gaji_id' => $transaksiGaji->id,
            'action' => 'viewed',
        ]);

        $totalTunjangan = $transaksiGaji->details->where('jenis_snapshot', 'Tunjangan')->sum('nominal_hasil');
        $totalPotongan = $transaksiGaji->details->where('jenis_snapshot', 'Potongan')->sum('nominal_hasil');

        return view('me.salary-slips.show', compact(
            'karyawan',
            'transaksiGaji',
            'totalTunjangan',
            'totalPotongan',
        ));
    }
}
