@extends('layouts.app')

@section('title', 'Laporan Penggajian - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header title="Laporan Penggajian" subtitle="Pembayaran gaji per periode dan unit kerja.">
            <x-slot:actions>
                <div class="d-flex gap-2 d-print-none">
                    <a class="btn btn-outline-primary" href="{{ route('laporan.penggajian.cetak', request()->query()) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i>
                        Cetak
                    </a>
                    <a class="btn btn-outline-success" href="{{ route('laporan.penggajian.export', request()->query()) }}">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export Excel
                    </a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-filter-card>
                <form action="{{ route('laporan.penggajian') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-5 col-xl-4">
                        <label class="form-label" for="unit_kerja_id">Unit Kerja</label>
                        <select class="form-select @error('unit_kerja_id') is-invalid @enderror" id="unit_kerja_id" name="unit_kerja_id">
                            <option value="">Semua unit kerja</option>
                            @foreach($unitKerjas as $unit)
                                <option value="{{ $unit->id }}" @selected((string) request('unit_kerja_id') === (string) $unit->id)>{{ $unit->nama_unit }}</option>
                            @endforeach
                        </select>
                        @error('unit_kerja_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
                        @if(request()->hasAny(['unit_kerja_id']))
                            <a class="btn btn-light" href="{{ route('laporan.penggajian', ['bulan' => $bulan, 'tahun' => $tahun]) }}">Reset</a>
                        @endif
                    </div>
                </form>
        </x-filter-card>

        <div class="report-stat-grid mb-4">
            <x-stat-card icon="bi-receipt" label="Total Transaksi" :value="number_format($totalTransaksi, 0, ',', '.')" plain />
            <x-stat-card icon="bi-cash-stack" label="Total Gaji Pokok" :value="'Rp '.number_format($totalGajiPokok, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-wallet2" label="Total Gaji Bersih" :value="'Rp '.number_format($totalGajiBersih, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-plus-circle" label="Total Tunjangan" :value="'Rp '.number_format($totalTunjangan, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-dash-circle" label="Total Potongan" :value="'Rp '.number_format($totalPotongan, 0, ',', '.')" compact plain />
        </div>

        <div class="card mb-4">
            <div class="card-header">Rekapitulasi per Unit Kerja</div>
            <div class="table-responsive">
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
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Detail Transaksi Gaji</div>
            <div class="table-responsive">
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
                            <x-empty-row :colspan="5">Tidak ada transaksi gaji yang cocok dengan filter.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination-footer :paginator="$transaksiGaji" class="d-print-none" />
        </div>

</x-app-page>
@endsection
