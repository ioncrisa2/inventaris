@extends('layouts.app')

@section('title', 'Detail Slip Gaji Saya')

@section('content')
<x-app-page>
    <x-page-header
        title="Slip Gaji {{ \Illuminate\Support\Carbon::create($transaksiGaji->tahun, $transaksiGaji->bulan, 1)->translatedFormat('F Y') }}"
        subtitle="{{ $karyawan->nama_lengkap }} — {{ $karyawan->nik }}"
    >
        <x-slot:actions><a class="btn btn-light" href="{{ route('me.salary-slips.index') }}">Kembali</a></x-slot:actions>
    </x-page-header>

    <div class="card content-narrow">
        <div class="card-body">
            <dl class="row g-3 mb-4">
                <dt class="col-sm-5 text-body-secondary">Gaji Pokok</dt><dd class="col-sm-7 text-end">Rp {{ number_format($transaksiGaji->gaji_pokok, 0, ',', '.') }}</dd>
                <dt class="col-sm-5 text-body-secondary">Total Tunjangan</dt><dd class="col-sm-7 text-end">Rp {{ number_format($totalTunjangan, 0, ',', '.') }}</dd>
                <dt class="col-sm-5 text-body-secondary">Total Potongan</dt><dd class="col-sm-7 text-end">Rp {{ number_format($totalPotongan, 0, ',', '.') }}</dd>
                <dt class="col-sm-5">Gaji Bersih</dt><dd class="col-sm-7 text-end fw-bold">Rp {{ number_format($transaksiGaji->gaji_bersih, 0, ',', '.') }}</dd>
            </dl>

            <h2 class="h6">Rincian Komponen</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Komponen</th><th>Jenis</th><th class="text-end">Nominal</th></tr></thead>
                    <tbody>
                        @forelse($transaksiGaji->details as $detail)
                            <tr><td>{{ $detail->nama_komponen_snapshot }}</td><td>{{ $detail->jenis_snapshot }}</td><td class="text-end">Rp {{ number_format($detail->nominal_hasil, 0, ',', '.') }}</td></tr>
                        @empty
                            <x-empty-row :colspan="3">Tidak ada rincian komponen.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-page>
@endsection
