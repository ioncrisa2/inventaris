@extends('layouts.app')

@section('title', 'Laporan Absensi - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        @php
            $absensiActiveFilterCount = (filled($selectedKaryawanId) ? 1 : 0)
                + ((int) $bulan !== now()->month ? 1 : 0)
                + ((int) $tahun !== now()->year ? 1 : 0);

            $absensiSelectedEmployee = filled($selectedKaryawanId)
                ? ($karyawans->firstWhere('id', (int) $selectedKaryawanId)?->nama_lengkap ?? 'Pegawai tidak tersedia')
                : 'Semua pegawai';

            $absensiPeriod = \Illuminate\Support\Carbon::createFromDate(2000, $bulan, 1)->translatedFormat('F').' '.$tahun;
        @endphp

        <x-page-header title="Laporan Absensi" subtitle="Kehadiran per pegawai dalam rentang yang dipilih.">
            <x-slot:actions>
                <div class="d-flex flex-wrap gap-2 d-print-none">
                    <x-report-filter-button
                        target="absensiReportFilter"
                        :active-count="$absensiActiveFilterCount"
                    />
                    @can('laporan.absensi.cetak')
                    <a class="btn btn-outline-primary" href="{{ route('laporan.absensi.cetak', request()->query()) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i>
                        Cetak
                    </a>
                    @endcan
                    @can('laporan.absensi.export')
                    <a class="btn btn-outline-success" href="{{ route('laporan.absensi.export', request()->query()) }}">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export Excel
                    </a>
                    @endcan
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-report-filter-summary
            :items="[
                ['label' => 'Pegawai', 'value' => $absensiSelectedEmployee],
                ['label' => 'Periode', 'value' => $absensiPeriod],
            ]"
        />

        <x-report-filter-modal
            id="absensiReportFilter"
            title="Filter Laporan Absensi"
            :action="route('laporan.absensi')"
            :reset-url="route('laporan.absensi')"
            :has-filters="$absensiActiveFilterCount > 0"
            :has-errors="$errors->any()"
        >
            <div class="col-12">
                <label class="form-label" for="absensi_karyawan_id">Pegawai</label>
                <select class="form-select @error('karyawan_id') is-invalid @enderror" id="absensi_karyawan_id" name="karyawan_id">
                    <option value="">Semua pegawai</option>
                    @foreach($karyawans as $karyawan)
                        <option value="{{ $karyawan->id }}" @selected((string) old('karyawan_id', $selectedKaryawanId) === (string) $karyawan->id)>
                            {{ $karyawan->nama_lengkap }}
                        </option>
                    @endforeach
                </select>
                @error('karyawan_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="absensi_bulan">Bulan</label>
                <select class="form-select @error('bulan') is-invalid @enderror" id="absensi_bulan" name="bulan">
                    @for($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}" @selected((int) old('bulan', $bulan) === $i)>
                            {{ \Illuminate\Support\Carbon::createFromDate(2000, $i, 1)->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>
                @error('bulan') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <label class="form-label" for="absensi_tahun">Tahun</label>
                <select class="form-select @error('tahun') is-invalid @enderror" id="absensi_tahun" name="tahun">
                    @for($y = date('Y'); $y >= 2020; $y--)
                        <option value="{{ $y }}" @selected((int) old('tahun', $tahun) === $y)>{{ $y }}</option>
                    @endfor
                </select>
                @error('tahun') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </x-report-filter-modal>

        <x-report-tabs
            label="Bagian laporan absensi"
            :tabs="[
                'absensi-ringkasan' => [
                    'label' => 'Ringkasan Kehadiran',
                    'description' => 'Total setiap status absensi.',
                    'icon' => 'bi-grid-1x2',
                ],
                'absensi-detail' => [
                    'label' => 'Detail Absensi',
                    'description' => 'Catatan absensi per pegawai.',
                    'icon' => 'bi-calendar-check',
                ],
            ]"
        >
            <x-report-tab-pane
                id="absensi-ringkasan"
                title="Ringkasan Kehadiran"
                description="Jumlah kehadiran dan ketidakhadiran pada periode yang dipilih."
                active
            >
        <div class="report-stat-grid">
            <x-stat-card icon="bi-person-check" label="Hadir" :value="number_format($totalHadir, 0, ',', '.')" plain />
            <x-stat-card icon="bi-envelope-check" label="Izin" :value="number_format($totalIzin, 0, ',', '.')" plain />
            <x-stat-card icon="bi-heart-pulse" label="Sakit" :value="number_format($totalSakit, 0, ',', '.')" plain />
            <x-stat-card icon="bi-calendar2-week" label="Cuti" :value="number_format($totalCuti, 0, ',', '.')" plain />
            <x-stat-card icon="bi-geo-alt" label="Dinas Luar Kota" :value="number_format($totalDinasLuarKota, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-person-x" label="Alpha" :value="number_format($totalAlpha, 0, ',', '.')" plain accent />
        </div>
            </x-report-tab-pane>

            <x-report-tab-pane
                id="absensi-detail"
                :title="'Detail Absensi - '.\Illuminate\Support\Carbon::createFromDate(2000, $bulan, 1)->translatedFormat('F').' '.$tahun"
                description="Daftar status dan catatan absensi pegawai pada periode laporan."
            >
            <div class="table-responsive report-tab-table">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Tanggal</th>
                            <th>Pegawai</th>
                            <th>Status</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($absensis as $absensi)
                            <tr>
                                <td>{{ $absensi->tanggal->translatedFormat('d F Y') }}</td>
                                <td>{{ $absensi->karyawan->nama_lengkap ?? '-' }}</td>
                                <td><x-badge :color="\App\Models\Absensi::STATUS_COLORS[$absensi->status] ?? 'bg-secondary'">{{ $absensi->status }}</x-badge></td>
                                <td>{{ $absensi->catatan ?? '-' }}</td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="4">Belum ada data absensi untuk periode ini.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination-footer :paginator="$absensis" class="d-print-none" />
            </x-report-tab-pane>
        </x-report-tabs>

</x-app-page>
@endsection
