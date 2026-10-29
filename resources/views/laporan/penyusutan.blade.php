@extends('layouts.app')

@section('title', 'Laporan Penyusutan - Sistem Inventaris & Kepegawaian')

@section('content')
@php
    $showTenant = auth()->user()->isSuperAdmin();
    $tahunBerjalan = \App\Support\PenyusutanCalculator::tahunBerjalan();
    $isProyeksi = (int) $tahun > $tahunBerjalan;
    $subtitlePeriode = 'Per 31 Des ' . $tahun . ($isProyeksi ? ' (proyeksi)' : '');

    $hasActiveFilters = ($selectedKoperasiId ? 1 : 0)
        + (request()->filled('unit_kerja_id') ? 1 : 0)
        + (request()->filled('kategori') ? 1 : 0)
        + ((int) $tahun !== $tahunBerjalan ? 1 : 0)
        + (request()->filled('search') ? 1 : 0);

    $excelUrl = route('laporan.penyusutan.export', request()->query());
    $printUrl = route('laporan.penyusutan.cetak', request()->query());
    $resetUrl = route('laporan.penyusutan', ['tahun' => $tahunBerjalan]);
@endphp

<x-report-layout
    title="Laporan Penyusutan"
    :excel-url="auth()->user()->can('laporan.penyusutan.export') ? $excelUrl : null"
    :print-url="auth()->user()->can('laporan.penyusutan.cetak') ? $printUrl : null"
    search-placeholder="Cari nama atau kode barang..."
    :has-active-filters="$hasActiveFilters > 0"
    :reset-url="$resetUrl"
    :paginator="$barangs"
    unit="aset"
>
    {{-- Slot Filter Bar --}}
    <x-slot:filters>
        @if($showTenant)
            <select class="form-select form-select-sm" name="koperasi_id" onchange="this.form.submit()" aria-label="Filter Koperasi">
                <option value="">Koperasi: Seluruh koperasi</option>
                @foreach($koperasis as $koperasi)
                    <option value="{{ $koperasi->id }}" @selected((string) $selectedKoperasiId === (string) $koperasi->id)>
                        Koperasi: {{ $koperasi->nama }}
                    </option>
                @endforeach
            </select>
        @endif

        <select class="form-select form-select-sm" name="tahun" onchange="this.form.submit()" aria-label="Filter Tahun">
            @for($y = $tahunBerjalan + 5; $y >= 2020; $y--)
                <option value="{{ $y }}" @selected((int) $tahun === $y)>
                    Tahun: {{ $y }}
                </option>
            @endfor
        </select>

        <select class="form-select form-select-sm" name="unit_kerja_id" onchange="this.form.submit()" aria-label="Filter Unit Kerja">
            <option value="">Unit: Semua unit kerja</option>
            @foreach($unitKerjas as $unit)
                <option value="{{ $unit->id }}" @selected((string) request('unit_kerja_id') === (string) $unit->id)>
                    Unit: {{ $showTenant ? (($unit->koperasi?->nama ?? 'Tanpa koperasi').' — '.$unit->nama_unit) : $unit->nama_unit }}
                </option>
            @endforeach
        </select>

        <select class="form-select form-select-sm" name="kategori" onchange="this.form.submit()" aria-label="Filter Golongan">
            <option value="">Golongan: Semua golongan</option>
            @foreach(config('inventaris.kategori') as $kategori)
                <option value="{{ $kategori }}" @selected(request('kategori') === $kategori)>
                    Golongan: {{ $kategori }}
                </option>
            @endforeach
        </select>
    </x-slot:filters>

    {{-- Slot Kartu Ringkasan --}}
    <x-slot:cards>
        <x-report-card
            label="Total Aset (masih bernilai)"
            :value="number_format($totalAset, 0, ',', '.')"
            :subtitle="$subtitlePeriode"
            icon="bi-box-seam"
        />
        <x-report-card
            label="Harga Perolehan"
            :value="'Rp ' . number_format($totalHargaPerolehan, 0, ',', '.')"
            :subtitle="$subtitlePeriode"
            icon="bi-cash-stack"
        />
        <x-report-card
            :label="'Penyusutan ' . $tahun"
            :value="'Rp ' . number_format($totalPenyusutanTahunIni, 0, ',', '.')"
            :subtitle="$subtitlePeriode"
            icon="bi-graph-down"
            accent
        />
        <x-report-card
            :label="'Akumulasi s/d ' . $tahun"
            :value="'Rp ' . number_format($totalAkumulasiPenyusutan, 0, ',', '.')"
            :subtitle="$subtitlePeriode"
            icon="bi-calculator"
        />
        <x-report-card
            label="Nilai Buku"
            :value="'Rp ' . number_format($totalNilaiBuku, 0, ',', '.')"
            :subtitle="$subtitlePeriode"
            icon="bi-wallet2"
        />
    </x-slot:cards>

    {{-- Slot Tabel Detail --}}
    <table class="report-table align-middle">
        <thead>
            <tr>
                <x-report-sort-th column="kode_barang" label="Barang" />
                @if($showTenant)
                    <th>Koperasi</th>
                @endif
                <x-report-sort-th column="harga_perolehan" label="Harga Perolehan" align="end" />
                <x-report-sort-th column="akumulasi_awal_tahun" :label="'Akum. s/d '.($tahun - 1)" align="end" />
                <x-report-sort-th column="penyusutan_tahun_ini" :label="'Penyusutan '.$tahun" align="end" />
                <x-report-sort-th column="akumulasi_akhir_tahun" :label="'Akum. s/d '.$tahun" align="end" />
                <x-report-sort-th column="nilai_buku" label="Nilai Buku" align="end" />
            </tr>
        </thead>
        <tbody>
            @forelse($barangs as $barang)
                @php
                    $r = $rincian->get($barang->id);
                    $hp = (float) $barang->harga_perolehan;
                    $nb = (float) ($r['nilai_buku_akhir_tahun'] ?? 0);
                    $persenSisa = $hp > 0 ? round(($nb / $hp) * 100, 1) : 0;
                    $detailUrl = route('barang.show', $barang) . '#tab-penyusutan';
                @endphp
                <tr>
                    <td>
                        <div class="report-cell-identity">
                            <a href="{{ $detailUrl }}" class="report-cell-identity__primary">
                                {{ $barang->nama_barang }}
                            </a>
                            <div class="report-cell-identity__secondary">
                                <span class="fw-medium font-monospace">{{ $barang->kode_barang }}</span>
                                <span class="text-body-tertiary">·</span>
                                <span>{{ $barang->kategori }}</span>
                            </div>
                        </div>
                    </td>
                    @if($showTenant)
                        <td class="report-cell-nowrap">{{ $barang->koperasi?->nama ?? 'Tanpa koperasi' }}</td>
                    @endif
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($barang->harga_perolehan, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($r['akumulasi_awal_tahun'] ?? 0, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($r['penyusutan_tahun_ini'] ?? 0, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($r['akumulasi_akhir_tahun'] ?? 0, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        <div>Rp {{ number_format($nb, 0, ',', '.') }}</div>
                        <small class="text-secondary">{{ $persenSisa }}% tersisa</small>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showTenant ? 7 : 6 }}" class="text-center py-5 text-body-secondary">
                        <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                        Tidak ada data yang cocok.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($totalAset > 0)
            <tfoot>
                <tr>
                    <td @if($showTenant) colspan="2" @endif>
                        Total semua aset ({{ number_format($totalAset, 0, ',', '.') }})
                    </td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($totalHargaPerolehan, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($totalAkumulasiAwalTahun, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($totalPenyusutanTahunIni, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($totalAkumulasiPenyusutan, 0, ',', '.') }}
                    </td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($totalNilaiBuku, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
</x-report-layout>
@endsection
