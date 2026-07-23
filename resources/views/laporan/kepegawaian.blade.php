@extends('layouts.app')

@section('title', 'Laporan Kepegawaian - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header title="Laporan Kepegawaian" subtitle="Sebaran tenaga kerja, status, dan gaji pokok.">
            <x-slot:actions>
                <div class="d-flex gap-2 d-print-none">
                    <a class="btn btn-outline-primary" href="{{ route('laporan.kepegawaian.cetak', request()->query()) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i>
                        Cetak
                    </a>
                    <a class="btn btn-outline-success" href="{{ route('laporan.kepegawaian.export', request()->query()) }}">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export Excel
                    </a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-filter-card>
                <form action="{{ route('laporan.kepegawaian') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label" for="unit_kerja_id">Unit Kerja</label>
                        <select class="form-select @error('unit_kerja_id') is-invalid @enderror" id="unit_kerja_id" name="unit_kerja_id">
                            <option value="">Semua unit kerja</option>
                            @foreach($unitKerjas as $unit)
                                <option value="{{ $unit->id }}" @selected((string) request('unit_kerja_id') === (string) $unit->id)>{{ $unit->nama_unit }}</option>
                            @endforeach
                        </select>
                        @error('unit_kerja_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="status_karyawan">Status</label>
                        <select class="form-select @error('status_karyawan') is-invalid @enderror" id="status_karyawan" name="status_karyawan">
                            <option value="">Semua status</option>
                            @foreach(\App\Models\Karyawan::STATUSES as $status)
                                <option value="{{ $status }}" @selected(request('status_karyawan') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status_karyawan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Terapkan</button>
                        @if(request()->hasAny(['unit_kerja_id', 'status_karyawan']))
                            <a class="btn btn-light" href="{{ route('laporan.kepegawaian') }}">Reset</a>
                        @endif
                    </div>
                </form>
        </x-filter-card>

        <div class="report-stat-grid mb-4">
            <x-stat-card icon="bi-people" label="Total Karyawan" :value="number_format($totalKaryawan, 0, ',', '.')" plain />
            <x-stat-card icon="bi-person-check" label="Karyawan Aktif" :value="number_format($totalAktif, 0, ',', '.')" plain />
            <x-stat-card icon="bi-cash-stack" label="Gaji Pokok Karyawan Aktif" :value="'Rp '.number_format($totalGajiAktif, 0, ',', '.')" compact plain />
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">Rekapitulasi Status</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Status</th><th class="text-end">Jumlah</th></tr>
                            </thead>
                            <tbody>
                                @forelse($rekapStatus as $rekap)
                                    <tr>
                                        <td>{{ $rekap->status_karyawan }}</td>
                                        <td class="text-end">{{ number_format($rekap->total_karyawan, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <x-empty-row :colspan="2">Tidak ada data status.</x-empty-row>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light fw-semibold">
                                <tr><td>Total</td><td class="text-end">{{ number_format($totalKaryawan, 0, ',', '.') }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header">Distribusi per Unit Kerja</div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr><th>Unit Kerja</th><th class="text-end">Jumlah Karyawan</th></tr>
                            </thead>
                            <tbody>
                                @forelse($rekapUnitKerja as $rekap)
                                    <tr>
                                        <td>{{ $rekap->unitKerja?->nama_unit ?? 'Tanpa unit kerja' }}</td>
                                        <td class="text-end">{{ number_format($rekap->total_karyawan, 0, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <x-empty-row :colspan="2">Tidak ada data unit kerja.</x-empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Detail Karyawan</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>NIK</th>
                            <th>Nama Lengkap</th>
                            <th>Unit Kerja</th>
                            <th>Jabatan</th>
                            <th>Status</th>
                            <th class="text-end">Gaji Pokok</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($karyawans as $karyawan)
                            <tr>
                                <td><strong>{{ $karyawan->nik }}</strong></td>
                                <td>{{ $karyawan->nama_lengkap }}</td>
                                <td>{{ $karyawan->unitKerja?->nama_unit ?? '—' }}</td>
                                <td>{{ $karyawan->jabatan }}</td>
                                <td><x-badge :color="\App\Models\Karyawan::STATUS_COLORS[$karyawan->status_karyawan] ?? 'bg-secondary'">{{ $karyawan->status_karyawan }}</x-badge></td>
                                <td class="text-end">Rp {{ number_format($karyawan->gaji_pokok, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <x-empty-row :colspan="6">Tidak ada karyawan yang cocok dengan filter.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination-footer :paginator="$karyawans" class="d-print-none" />
        </div>

</x-app-page>
@endsection
