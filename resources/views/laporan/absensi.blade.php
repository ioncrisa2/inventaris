@extends('layouts.app')

@section('title', 'Laporan Absensi - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header title="Laporan Absensi" subtitle="Kehadiran per pegawai dalam rentang yang dipilih.">
            <x-slot:actions>
                <div class="d-flex gap-2 d-print-none">
                    <a class="btn btn-outline-primary" href="{{ route('laporan.absensi.cetak', request()->query()) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i>
                        Cetak
                    </a>
                    <a class="btn btn-outline-success" href="{{ route('laporan.absensi.export', request()->query()) }}">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export Excel
                    </a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-filter-card>
                <form action="{{ route('laporan.absensi') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6 col-xl-4">
                        <label class="form-label" for="karyawan_id">Pegawai</label>
                        <select class="form-select @error('karyawan_id') is-invalid @enderror" id="karyawan_id" name="karyawan_id">
                            <option value="">Semua pegawai</option>
                            @foreach($karyawans as $karyawan)
                                <option value="{{ $karyawan->id }}" @selected((string) $selectedKaryawanId === (string) $karyawan->id)>
                                    {{ $karyawan->nama_lengkap }}
                                </option>
                            @endforeach
                        </select>
                        @error('karyawan_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 col-xl-2">
                        <label class="form-label" for="bulan">Bulan</label>
                        <select class="form-select @error('bulan') is-invalid @enderror" id="bulan" name="bulan">
                            @for($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}" @selected((int) $bulan === $i)>
                                    {{ \Illuminate\Support\Carbon::createFromDate(2000, $i, 1)->translatedFormat('F') }}
                                </option>
                            @endfor
                        </select>
                        @error('bulan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 col-xl-2">
                        <label class="form-label" for="tahun">Tahun</label>
                        <select class="form-select @error('tahun') is-invalid @enderror" id="tahun" name="tahun">
                            @for($y = date('Y'); $y >= 2020; $y--)
                                <option value="{{ $y }}" @selected((int) $tahun === $y)>{{ $y }}</option>
                            @endfor
                        </select>
                        @error('tahun') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-xl-4 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Terapkan</button>
                        @if(request()->hasAny(['karyawan_id', 'bulan', 'tahun']))
                            <a class="btn btn-light" href="{{ route('laporan.absensi') }}">Reset</a>
                        @endif
                    </div>
                </form>
        </x-filter-card>

        <div class="report-stat-grid mb-4">
            <x-stat-card icon="bi-person-check" label="Hadir" :value="number_format($totalHadir, 0, ',', '.')" plain />
            <x-stat-card icon="bi-envelope-check" label="Izin" :value="number_format($totalIzin, 0, ',', '.')" plain />
            <x-stat-card icon="bi-heart-pulse" label="Sakit" :value="number_format($totalSakit, 0, ',', '.')" plain />
            <x-stat-card icon="bi-calendar2-week" label="Cuti" :value="number_format($totalCuti, 0, ',', '.')" plain />
            <x-stat-card icon="bi-geo-alt" label="Dinas Luar Kota" :value="number_format($totalDinasLuarKota, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-person-x" label="Alpha" :value="number_format($totalAlpha, 0, ',', '.')" plain accent />
        </div>

        <div class="card">
            <div class="card-header">Detail Absensi - {{ \Illuminate\Support\Carbon::createFromDate(2000, $bulan, 1)->translatedFormat('F') }} {{ $tahun }}</div>
            <div class="table-responsive">
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
        </div>

</x-app-page>
@endsection
