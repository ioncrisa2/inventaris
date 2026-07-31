@extends('layouts.app')

@section('title', 'Laporan Penggajian - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        @php
            $penggajianActiveFilterCount = (request()->filled('unit_kerja_id') ? 1 : 0)
                + ((int) $bulan !== now()->month ? 1 : 0)
                + ((int) $tahun !== now()->year ? 1 : 0);

            $penggajianSelectedUnit = request()->filled('unit_kerja_id')
                ? ($unitKerjas->firstWhere('id', (int) request('unit_kerja_id'))?->nama_unit ?? 'Unit tidak tersedia')
                : 'Semua unit kerja';

            $penggajianPeriod = \Illuminate\Support\Carbon::createFromDate(2000, $bulan, 1)->translatedFormat('F').' '.$tahun;
        @endphp

        <x-page-header title="Laporan Penggajian" subtitle="Pembayaran gaji per periode dan unit kerja.">
            <x-slot:actions>
                <div class="d-flex flex-wrap gap-2 d-print-none">
                    <x-report-filter-button
                        target="penggajianReportFilter"
                        :active-count="$penggajianActiveFilterCount"
                    />
                    @can('laporan.penggajian.cetak')
                    <a class="btn btn-outline-primary" href="{{ route('laporan.penggajian.cetak', request()->query()) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i>
                        Cetak
                    </a>
                    @endcan
                    @can('laporan.penggajian.export')
                    <a class="btn btn-outline-success" href="{{ route('laporan.penggajian.export', request()->query()) }}">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export Excel
                    </a>
                    @endcan
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-report-filter-summary
            :items="[
                ['label' => 'Periode', 'value' => $penggajianPeriod],
                ['label' => 'Unit', 'value' => $penggajianSelectedUnit],
            ]"
        />

        <x-report-filter-modal
            id="penggajianReportFilter"
            title="Filter Laporan Penggajian"
            :action="route('laporan.penggajian')"
            :reset-url="route('laporan.penggajian')"
            :has-filters="$penggajianActiveFilterCount > 0"
            :has-errors="$errors->any()"
        >
            <div class="col-12">
                <label class="form-label" for="penggajian_unit_kerja_id">Unit Kerja</label>
                <select class="form-select @error('unit_kerja_id') is-invalid @enderror" id="penggajian_unit_kerja_id" name="unit_kerja_id">
                    <option value="">Semua unit kerja</option>
                    @foreach($unitKerjas as $unit)
                        <option value="{{ $unit->id }}" @selected((string) old('unit_kerja_id', request('unit_kerja_id')) === (string) $unit->id)>
                            {{ $unit->nama_unit }}
                        </option>
                    @endforeach
                </select>
                @error('unit_kerja_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="penggajian_bulan">Bulan</label>
                <select class="form-select @error('bulan') is-invalid @enderror" id="penggajian_bulan" name="bulan">
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}" @selected((int) old('bulan', $bulan) === $i)>
                            {{ \Illuminate\Support\Carbon::createFromDate(2000, $i, 1)->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>
                @error('bulan') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="penggajian_tahun">Tahun</label>
                <select class="form-select @error('tahun') is-invalid @enderror" id="penggajian_tahun" name="tahun">
                    @for($y = date('Y'); $y >= 2020; $y--)
                        <option value="{{ $y }}" @selected((int) old('tahun', $tahun) === $y)>{{ $y }}</option>
                    @endfor
                </select>
                @error('tahun') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </x-report-filter-modal>

        <x-report-tabs
            label="Bagian laporan penggajian"
            :tabs="[
                'penggajian-ringkasan' => [
                    'label' => 'Ringkasan',
                    'description' => 'Ikhtisar nominal penggajian.',
                    'icon' => 'bi-grid-1x2',
                ],
                'penggajian-unit' => [
                    'label' => 'Rekap per Unit',
                    'description' => 'Transaksi dan gaji bersih per unit.',
                    'icon' => 'bi-diagram-3',
                ],
                'penggajian-detail' => [
                    'label' => 'Detail Transaksi',
                    'description' => 'Daftar transaksi pada periode ini.',
                    'icon' => 'bi-table',
                ],
            ]"
        >
            <x-report-tab-pane
                id="penggajian-ringkasan"
                title="Ringkasan Penggajian"
                description="Nilai utama penggajian pada periode dan unit kerja yang dipilih."
                active
            >
        <div class="report-stat-grid">
            <x-stat-card icon="bi-receipt" label="Total Transaksi" :value="number_format($totalTransaksi, 0, ',', '.')" plain />
            <x-stat-card icon="bi-cash-stack" label="Total Gaji Pokok" :value="'Rp '.number_format($totalGajiPokok, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-wallet2" label="Total Gaji Bersih" :value="'Rp '.number_format($totalGajiBersih, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-plus-circle" label="Total Tunjangan" :value="'Rp '.number_format($totalTunjangan, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-dash-circle" label="Total Potongan" :value="'Rp '.number_format($totalPotongan, 0, ',', '.')" compact plain />
        </div>
            </x-report-tab-pane>

            <x-report-tab-pane
                id="penggajian-unit"
                title="Rekapitulasi per Unit Kerja"
                description="Jumlah transaksi dan total gaji bersih pada setiap unit kerja."
            >
            <div class="table-responsive report-tab-table">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Unit Kerja</th>
                            <th class="text-end">Jumlah Transaksi</th>
                            <th class="text-end">Total Gaji Bersih</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rekapUnitKerja as $rekap)
                            <tr>
                                <td>{{ $rekap->nama_unit }}</td>
                                <td class="text-end">{{ number_format($rekap->total_transaksi, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($rekap->total_gaji_bersih, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="3">Tidak ada transaksi gaji pada periode ini.</x-empty-row>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th scope="row">Total Keseluruhan</th>
                            <th class="text-end">{{ number_format($totalTransaksi, 0, ',', '.') }}</th>
                            <th class="text-end">Rp {{ number_format($totalGajiBersih, 0, ',', '.') }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            </x-report-tab-pane>

            <x-report-tab-pane
                id="penggajian-detail"
                title="Detail Transaksi Gaji"
                description="Daftar transaksi gaji yang cocok dengan periode dan filter laporan."
            >
            <div class="table-responsive report-tab-table">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Karyawan</th>
                            <th>Unit Kerja</th>
                            <th class="text-end">Gaji Pokok</th>
                            <th class="text-end">Gaji Bersih</th>
                            @can('transaksi-gaji.view')
                            <th class="text-nowrap table-col-width-100">Aksi</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transaksiGaji as $transaksi)
                            <tr>
                                <td>{{ $transaksi->karyawan?->nama_lengkap ?? '—' }}</td>
                                <td>{{ $transaksi->karyawan?->unitKerja?->nama_unit ?? '—' }}</td>
                                <td class="text-end">Rp {{ number_format($transaksi->gaji_pokok, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($transaksi->gaji_bersih, 0, ',', '.') }}</td>
                                @can('transaksi-gaji.view')
                                <td>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('transaksi-gaji.show', $transaksi) }}">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                                @endcan
                            </tr>
                        @empty
                            <x-empty-row :colspan="auth()->user()->can('transaksi-gaji.view') ? 5 : 4">Tidak ada transaksi gaji yang cocok dengan filter.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination-footer :paginator="$transaksiGaji" class="d-print-none" />
            </x-report-tab-pane>
        </x-report-tabs>

</x-app-page>
@endsection
