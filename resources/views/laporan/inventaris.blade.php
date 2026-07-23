@extends('layouts.app')

@section('title', 'Laporan Inventaris - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header title="Laporan Inventaris" subtitle="Nilai aset dan kondisi terakhir sesuai filter.">
            <x-slot:actions>
                <div class="d-flex gap-2 d-print-none">
                    <a class="btn btn-outline-primary" href="{{ route('laporan.inventaris.cetak', request()->query()) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i>
                        Cetak
                    </a>
                    <a class="btn btn-outline-success" href="{{ route('laporan.inventaris.export', request()->query()) }}">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export Excel
                    </a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-filter-card>
                <form action="{{ route('laporan.inventaris') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label" for="unit_kerja_id">Unit Kerja</label>
                        <select class="form-select @error('unit_kerja_id') is-invalid @enderror" id="unit_kerja_id" name="unit_kerja_id">
                            <option value="">Semua unit kerja</option>
                            @foreach($unitKerjas as $unit)
                            <option value="{{ $unit->id }}" @selected((string) request('unit_kerja_id')===(string) $unit->id)>{{ $unit->nama_unit }}</option>
                            @endforeach
                        </select>
                        @error('unit_kerja_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 col-xl-3">
                        <label class="form-label" for="kategori">Golongan</label>
                        <select class="form-select @error('kategori') is-invalid @enderror" id="kategori" name="kategori">
                            <option value="">Semua golongan</option>
                            @foreach(config('inventaris.kategori') as $kategori)
                            <option value="{{ $kategori }}"
                                @selected(request('kategori')===$kategori)>
                                {{ $kategori }}
                            </option>
                            @endforeach
                        </select>
                        @error('kategori') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 col-xl-2">
                        <x-form.input name="tanggal_awal" label="Dari Tanggal" type="date" :value="request('tanggal_awal')" />
                    </div>

                    <div class="col-md-6 col-xl-2">
                        <x-form.input name="tanggal_akhir" label="Sampai Tanggal" type="date" :value="request('tanggal_akhir')" />
                    </div>

                    <div class="col-xl-2 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Terapkan</button>
                        @if(request()->hasAny(['unit_kerja_id', 'kategori', 'tanggal_awal', 'tanggal_akhir']))
                        <a class="btn btn-light" href="{{ route('laporan.inventaris') }}">Reset</a>
                        @endif
                    </div>
                </form>
        </x-filter-card>

        <div class="report-stat-grid mb-4">
            <x-stat-card icon="bi-box-seam" label="Total Barang" :value="number_format($totalBarang, 0, ',', '.')" plain />
            <x-stat-card icon="bi-cash-stack" label="Total Nilai Perolehan" :value="'Rp '.number_format($totalNilai, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-tools" label="Perlu Perbaikan" :value="number_format($barangPerluPerbaikan, 0, ',', '.')" plain accent />
        </div>

        <div class="card mb-4">
            <div class="card-header">Rekapitulasi per Golongan</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Golongan</th>
                            <th class="text-end">Jumlah Barang</th>
                            <th class="text-end">Total Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rekapKategori as $rekap)
                        <tr>
                            <td>{{ $rekap->kategori }}</td>
                            <td class="text-end">{{ number_format($rekap->total_barang, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($rekap->total_nilai, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <x-empty-row :colspan="3">Tidak ada data untuk direkap.</x-empty-row>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td>Total</td>
                            <td class="text-end">{{ number_format($totalBarang, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($totalNilai, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Detail Inventaris</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th>Golongan</th>
                            <th>Unit Kerja</th>
                            <th>Tanggal Perolehan</th>
                            <th>Kondisi</th>
                            <th class="text-end">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($barangs as $barang)
                        @php
                        $kondisi = $barang->kondisiTerakhir?->kondisi ?? 'Belum diperiksa';
                        @endphp
                        <tr>
                            <td><strong>{{ $barang->kode_barang }}</strong></td>
                            <td>{{ $barang->nama_barang }}</td>
                            <td>{{ $barang->kategori }}</td>
                            <td>{{ $barang->unitKerja?->nama_unit ?? '—' }}</td>
                            <td>{{ $barang->tanggal_perolehan->format('d/m/Y') }}</td>
                            <td><x-badge :color="config('inventaris.kondisi_warna')[$kondisi] ?? 'bg-secondary'">{{ $kondisi }}</x-badge></td>
                            <td class="text-end">Rp {{ number_format($barang->harga_perolehan, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <x-empty-row :colspan="7">Tidak ada inventaris yang cocok dengan filter.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination-footer :paginator="$barangs" class="d-print-none" />
        </div>

</x-app-page>
@endsection
