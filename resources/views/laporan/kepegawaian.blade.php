@extends('layouts.app')

@section('title', 'Laporan Kepegawaian - Sistem Inventaris & Kepegawaian')

@section('content')
@php
    $showTenant = auth()->user()->isSuperAdmin();
    $hasActiveFilters = request()->filled('unit_kerja_id')
        || request()->filled('status_karyawan')
        || request()->filled('keaktifan')
        || request()->filled('search')
        || (request()->filled('koperasi_id') && $showTenant);

    $excelUrl = auth()->user()->can('laporan.kepegawaian.export')
        ? route('laporan.kepegawaian.export', request()->query())
        : null;

    $printUrl = auth()->user()->can('laporan.kepegawaian.cetak')
        ? route('laporan.kepegawaian.cetak', request()->query())
        : null;
@endphp

<x-report-layout
    title="Laporan Kepegawaian"
    subtitle="Ringkasan status kepegawaian, penempatan unit kerja, dan beban gaji pokok karyawan."
    :excel-url="$excelUrl"
    :print-url="$printUrl"
    :paginator="$karyawans"
    unit="karyawan"
    search-name="search"
    :search-value="request('search')"
    search-placeholder="Cari nama / NIK karyawan..."
    :has-active-filters="$hasActiveFilters"
    :reset-url="route('laporan.kepegawaian')"
>
    {{-- Slot Filter Bar --}}
    <x-slot:filters>
        @if($showTenant)
            <select
                name="koperasi_id"
                class="form-select form-select-sm"
                aria-label="Filter Koperasi"
                style="min-width: 170px;"
                onchange="this.form.submit()"
            >
                <option value="">Koperasi: Semua</option>
                @foreach($koperasis as $koperasi)
                    <option value="{{ $koperasi->id }}" @selected((string) request('koperasi_id') === (string) $koperasi->id)>
                        {{ $koperasi->nama }}
                    </option>
                @endforeach
            </select>
        @endif

        {{-- Dropdown Unit Kerja --}}
        <select
            name="unit_kerja_id"
            class="form-select form-select-sm"
            aria-label="Filter Unit Kerja"
            style="min-width: 170px;"
            onchange="this.form.submit()"
        >
            <option value="">Unit Kerja: Semua</option>
            @foreach($unitKerjas as $unit)
                <option value="{{ $unit->id }}" @selected((string) request('unit_kerja_id') === (string) $unit->id)>
                    {{ $showTenant ? (($unit->koperasi?->nama ?? 'Tanpa koperasi').' — '.$unit->nama_unit) : $unit->nama_unit }}
                </option>
            @endforeach
        </select>

        {{-- Dropdown Status Kepegawaian --}}
        <select
            name="status_karyawan"
            class="form-select form-select-sm"
            aria-label="Filter Status Kepegawaian"
            style="min-width: 160px;"
            onchange="this.form.submit()"
        >
            <option value="">Status: Semua</option>
            @foreach(\App\Models\Karyawan::STATUSES as $status)
                <option value="{{ $status }}" @selected(request('status_karyawan') === $status)>
                    {{ $status }}
                </option>
            @endforeach
        </select>

        {{-- Dropdown Keaktifan --}}
        <select
            name="keaktifan"
            class="form-select form-select-sm"
            aria-label="Filter Keaktifan"
            style="min-width: 150px;"
            onchange="this.form.submit()"
        >
            <option value="">Keaktifan: Semua</option>
            <option value="aktif" @selected(request('keaktifan') === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected(request('keaktifan') === 'nonaktif')>Nonaktif</option>
        </select>
    </x-slot:filters>

    {{-- Slot Kartu Ringkasan --}}
    <x-slot:cards>
        <x-report-card
            label="Total Karyawan"
            :value="number_format($totalKaryawan, 0, ',', '.')"
            icon="bi-people"
        />
        <x-report-card
            label="Karyawan Aktif"
            :value="number_format($totalAktif, 0, ',', '.')"
            icon="bi-person-check"
        />
        <x-report-card
            label="Gaji Pokok (karyawan aktif)"
            :value="'Rp ' . number_format($totalGajiAktif, 0, ',', '.')"
            icon="bi-cash-stack"
        />
    </x-slot:cards>

    {{-- Slot Panel Rekap (Berdampingan: Rekap Status & Distribusi Unit) --}}
    <x-slot:recap>
        <x-report-recap-panel title="Rekap Status Kepegawaian">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Status Kepegawaian</th>
                        <th class="text-end">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rekapStatus as $rekap)
                        <tr>
                            <td>
                                <x-badge :color="\App\Models\Karyawan::STATUS_COLORS[$rekap->status_karyawan] ?? 'bg-secondary'">
                                    {{ $rekap->status_karyawan }}
                                </x-badge>
                            </td>
                            <td class="text-end tabular-nums">{{ number_format($rekap->total_karyawan, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center py-3 text-body-secondary">Tidak ada data status.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($totalKaryawan > 0)
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="text-end tabular-nums">{{ number_format($totalKaryawan, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </x-report-recap-panel>

        <x-report-recap-panel title="Distribusi Unit Kerja">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Unit Kerja</th>
                        <th class="text-end">Jumlah Karyawan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rekapUnitKerja as $rekap)
                        <tr>
                            <td>
                                {{ $showTenant ? (($rekap->unitKerja?->koperasi?->nama ?? 'Tanpa koperasi').' — '.($rekap->unitKerja?->nama_unit ?? 'Tanpa unit kerja')) : ($rekap->unitKerja?->nama_unit ?? 'Tanpa unit kerja') }}
                            </td>
                            <td class="text-end tabular-nums">{{ number_format($rekap->total_karyawan, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center py-3 text-body-secondary">Tidak ada data unit kerja.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($totalKaryawan > 0)
                    <tfoot>
                        <tr>
                            <td>Total</td>
                            <td class="text-end tabular-nums">{{ number_format($totalKaryawan, 0, ',', '.') }}</td>
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
                <x-report-sort-th column="nik" label="Karyawan" default-direction="asc" />
                @if($showTenant)
                    <th>Koperasi</th>
                @endif
                <x-report-sort-th column="unit_kerja" label="Unit Kerja" />
                <x-report-sort-th column="jabatan" label="Jabatan" />
                <x-report-sort-th column="status_karyawan" label="Status Kepegawaian" />
                <x-report-sort-th column="keaktifan" label="Keaktifan" />
                <x-report-sort-th column="gaji_pokok" label="Gaji Pokok" align="end" />
            </tr>
        </thead>
        <tbody>
            @forelse($karyawans as $karyawan)
                <tr>
                    {{-- 2-line cell: Nama tebal (link) + NIK --}}
                    <td>
                        <div class="report-cell-identity">
                            <a href="{{ route('karyawan.show', $karyawan->id) }}" class="report-cell-identity__primary">
                                {{ $karyawan->nama_lengkap }}
                            </a>
                            <span class="report-cell-identity__secondary">{{ $karyawan->nik }}</span>
                        </div>
                    </td>
                    @if($showTenant)
                        <td class="text-nowrap">{{ $karyawan->koperasi?->nama ?? 'Tanpa koperasi' }}</td>
                    @endif
                    <td class="text-nowrap">{{ $karyawan->unitKerja?->nama_unit ?? '—' }}</td>
                    <td class="text-nowrap" title="{{ $karyawan->jabatan }}">{{ $karyawan->jabatan }}</td>
                    <td class="text-nowrap">
                        <x-badge :color="\App\Models\Karyawan::STATUS_COLORS[$karyawan->status_karyawan] ?? 'bg-secondary'">
                            {{ $karyawan->status_karyawan }}
                        </x-badge>
                    </td>
                    <td class="text-nowrap">
                        @if($karyawan->tanggal_mengundurkan_diri === null)
                            <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle">
                                <i class="bi bi-check-circle me-1" aria-hidden="true"></i>Aktif
                            </span>
                        @else
                            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle" title="Mengundurkan diri: {{ $karyawan->tanggal_mengundurkan_diri }}">
                                <i class="bi bi-dash-circle me-1" aria-hidden="true"></i>Nonaktif
                            </span>
                        @endif
                    </td>
                    <td class="text-end tabular-nums text-nowrap">
                        Rp {{ number_format($karyawan->gaji_pokok, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $showTenant ? 7 : 6 }}" class="text-center py-4 text-body-secondary">
                        @if(filled(request('search')))
                            Tidak ada data yang cocok.
                        @else
                            Tidak ada data karyawan sesuai filter.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($totalKaryawan > 0)
            <tfoot>
                <tr>
                    <td colspan="{{ $showTenant ? 6 : 5 }}">
                        Total Gaji Pokok (karyawan aktif)
                    </td>
                    <td class="text-end tabular-nums text-nowrap">
                        Rp {{ number_format($totalGajiAktif, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>
</x-report-layout>
@endsection
