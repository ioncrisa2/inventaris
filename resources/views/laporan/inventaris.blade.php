@extends('layouts.app')

@section('title', 'Laporan Inventaris - Sistem Inventaris & Kepegawaian')

@section('content')
@php
    $showTenant = auth()->user()->isSuperAdmin();

    $hasActiveFilters = ($selectedKoperasiId ? 1 : 0)
        + (request()->filled('unit_kerja_id') ? 1 : 0)
        + (request()->filled('kategori') ? 1 : 0)
        + (request()->filled('kondisi') ? 1 : 0)
        + (request()->filled('tanggal_awal') ? 1 : 0)
        + (request()->filled('tanggal_akhir') ? 1 : 0)
        + (request()->filled('search') ? 1 : 0);

    $excelUrl = route('laporan.inventaris.export', request()->query());
    $printUrl = route('laporan.inventaris.cetak', request()->query());
    $resetUrl = route('laporan.inventaris');
@endphp

<x-report-layout
    title="Laporan Inventaris"
    :excel-url="auth()->user()->can('laporan.inventaris.export') ? $excelUrl : null"
    :print-url="auth()->user()->can('laporan.inventaris.cetak') ? $printUrl : null"
    search-placeholder="Cari nama, kode, atau jenis barang..."
    :has-active-filters="$hasActiveFilters > 0"
    :reset-url="$resetUrl"
    :paginator="$barangs"
    unit="barang"
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

        <select class="form-select form-select-sm" name="kondisi" onchange="this.form.submit()" aria-label="Filter Kondisi">
            <option value="">Kondisi: Semua kondisi</option>
            @foreach(config('inventaris.kondisi') as $kondisi)
                <option value="{{ $kondisi }}" @selected(request('kondisi') === $kondisi)>
                    Kondisi: {{ $kondisi }}
                </option>
            @endforeach
        </select>

        {{-- Tanggal Perolehan Dropdown / Inputs --}}
        <div class="d-inline-flex align-items-center gap-1">
            <input
                type="date"
                name="tanggal_awal"
                value="{{ request('tanggal_awal') }}"
                class="form-control form-control-sm"
                title="Tanggal Perolehan Awal"
                aria-label="Tanggal Perolehan Awal"
                style="width: 135px;"
                onchange="this.form.submit()"
            >
            <span class="text-secondary small">s.d.</span>
            <input
                type="date"
                name="tanggal_akhir"
                value="{{ request('tanggal_akhir') }}"
                class="form-control form-control-sm"
                title="Tanggal Perolehan Akhir"
                aria-label="Tanggal Perolehan Akhir"
                style="width: 135px;"
                onchange="this.form.submit()"
            >
        </div>
    </x-slot:filters>

    {{-- Slot Kartu Ringkasan --}}
    <x-slot:cards>
        <x-report-card
            label="Total Barang (masih bernilai)"
            :value="number_format($totalBarang, 0, ',', '.')"
            icon="bi-box-seam"
        />
        <x-report-card
            label="Total Nilai Buku"
            :value="'Rp ' . number_format($totalNilai, 0, ',', '.')"
            icon="bi-cash-stack"
        />
        <x-report-card
            label="Perlu Perbaikan"
            :value="number_format($barangPerluPerbaikan, 0, ',', '.')"
            icon="bi-tools"
            accent
        />
    </x-slot:cards>

    {{-- Slot Panel Rekap (Rekap per Golongan) --}}
    <x-slot:recap>
        <x-report-recap-panel title="Rekap per Golongan">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Golongan</th>
                        <th class="text-end">Jumlah Barang</th>
                        <th class="text-end">Total Nilai Buku</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rekapKategori as $rekap)
                        <tr>
                            <td>{{ $rekap->kategori }}</td>
                            <td class="text-end tabular-nums">{{ number_format($rekap->total_barang, 0, ',', '.') }}</td>
                            <td class="text-end tabular-nums">Rp {{ number_format($rekap->total_nilai, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center py-3 text-body-secondary">Tidak ada data untuk direkap.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($totalBarangTerekap > 0)
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="text-end tabular-nums">{{ number_format($totalBarangTerekap, 0, ',', '.') }}</td>
                            <td class="text-end tabular-nums">Rp {{ number_format($totalNilai, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </x-report-recap-panel>
    </x-slot:recap>

    {{-- Slot Tabel Detail --}}
    <table class="report-table align-middle">
        <thead>
            <tr>
                <x-report-sort-th column="kode_barang" label="Barang" />
                @if($showTenant)
                    <th>Koperasi</th>
                @endif
                <x-report-sort-th column="kategori" label="Golongan" />
                <x-report-sort-th column="unit_kerja" label="Unit Kerja" />
                <x-report-sort-th column="tanggal_perolehan" label="Tanggal Perolehan" />
                <x-report-sort-th column="kondisi" label="Kondisi" />
                <x-report-sort-th column="nilai_buku" label="Nilai Buku" align="end" />
            </tr>
        </thead>
        <tbody>
            @forelse($barangs as $barang)
                @php
                    $kondisi = $barang->kondisiTerakhir?->kondisi ?? 'Belum diperiksa';
                    $badgeClass = \App\Support\KondisiBarangHelper::badgeClass($kondisi);
                    $nilaiBuku = $barang->nilaiBukuTerakhir();
                @endphp
                <tr>
                    <td>
                        <div class="report-cell-identity">
                            <a href="{{ route('barang.show', $barang) }}" class="report-cell-identity__primary">
                                {{ $barang->nama_barang }}
                            </a>
                            <div class="report-cell-identity__secondary">
                                <span class="fw-medium font-monospace">{{ $barang->kode_barang }}</span>
                                <span class="text-body-tertiary">·</span>
                                <span>{{ $barang->jenis_barang ?? '—' }}</span>
                            </div>
                        </div>
                    </td>
                    @if($showTenant)
                        <td class="report-cell-nowrap">{{ $barang->koperasi?->nama ?? 'Tanpa koperasi' }}</td>
                    @endif
                    <td class="report-cell-nowrap">{{ $barang->kategori }}</td>
                    <td class="report-cell-nowrap">{{ $barang->unitKerja?->nama_unit ?? '—' }}</td>
                    <td class="report-cell-nowrap tabular-nums">{{ $barang->tanggal_perolehan->format('d/m/Y') }}</td>
                    <td>
                        <span class="badge {{ $badgeClass }}">{{ $kondisi }}</span>
                    </td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($nilaiBuku, 0, ',', '.') }}
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
        @if($totalBarang > 0)
            <tfoot>
                <tr>
                    <td @if($showTenant) colspan="2" @endif>
                        Total semua barang ({{ number_format($totalBarang, 0, ',', '.') }})
                    </td>
                    <td colspan="4"></td>
                    <td class="text-end tabular-nums report-cell-nowrap">
                        Rp {{ number_format($totalNilai, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
</x-report-layout>
@endsection
