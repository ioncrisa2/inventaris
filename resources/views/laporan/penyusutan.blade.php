@extends('layouts.app')

@section('title', 'Laporan Penyusutan - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        @php
            $penyusutanActiveFilterCount = (request()->filled('unit_kerja_id') ? 1 : 0)
                + (request()->filled('kategori') ? 1 : 0)
                + ((int) $tahun !== now()->year ? 1 : 0);

            $penyusutanSelectedUnit = request()->filled('unit_kerja_id')
                ? ($unitKerjas->firstWhere('id', (int) request('unit_kerja_id'))?->nama_unit ?? 'Unit tidak tersedia')
                : 'Semua unit kerja';
        @endphp

        <x-page-header title="Laporan Penyusutan" subtitle="Rekap penyusutan fiskal aset per tahun — dasar pelaporan SPT.">
            <x-slot:actions>
                <div class="d-flex flex-wrap gap-2 d-print-none">
                    <x-report-filter-button
                        target="penyusutanReportFilter"
                        :active-count="$penyusutanActiveFilterCount"
                    />
                    @can('laporan.penyusutan.cetak')
                    <a class="btn btn-outline-primary" href="{{ route('laporan.penyusutan.cetak', request()->query()) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i>
                        Cetak
                    </a>
                    @endcan
                    @can('laporan.penyusutan.export')
                    <a class="btn btn-outline-success" href="{{ route('laporan.penyusutan.export', request()->query()) }}">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export Excel
                    </a>
                    @endcan
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-report-filter-summary
            :items="[
                ['label' => 'Tahun', 'value' => $tahun],
                ['label' => 'Unit', 'value' => $penyusutanSelectedUnit],
                ['label' => 'Golongan', 'value' => request('kategori') ?: 'Semua golongan'],
            ]"
        />

        <x-report-filter-modal
            id="penyusutanReportFilter"
            title="Filter Laporan Penyusutan"
            :action="route('laporan.penyusutan')"
            :reset-url="route('laporan.penyusutan')"
            :has-filters="$penyusutanActiveFilterCount > 0"
            :has-errors="$errors->any()"
        >
            <div class="col-md-4">
                <label class="form-label" for="penyusutan_tahun">Tahun</label>
                <input
                    type="number"
                    name="tahun"
                    id="penyusutan_tahun"
                    class="form-control @error('tahun') is-invalid @enderror"
                    value="{{ old('tahun', request('tahun', $tahun)) }}"
                    min="2000"
                    max="2100"
                    required
                >
                @error('tahun') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="penyusutan_unit_kerja_id">Unit Kerja</label>
                <select class="form-select @error('unit_kerja_id') is-invalid @enderror" id="penyusutan_unit_kerja_id" name="unit_kerja_id">
                    <option value="">Semua unit kerja</option>
                    @foreach($unitKerjas as $unit)
                        <option value="{{ $unit->id }}" @selected((string) old('unit_kerja_id', request('unit_kerja_id')) === (string) $unit->id)>
                            {{ $unit->nama_unit }}
                        </option>
                    @endforeach
                </select>
                @error('unit_kerja_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-4">
                <label class="form-label" for="penyusutan_kategori">Golongan</label>
                <select class="form-select @error('kategori') is-invalid @enderror" id="penyusutan_kategori" name="kategori">
                    <option value="">Semua golongan</option>
                    @foreach(config('inventaris.kategori') as $kategori)
                        <option value="{{ $kategori }}" @selected(old('kategori', request('kategori')) === $kategori)>
                            {{ $kategori }}
                        </option>
                    @endforeach
                </select>
                @error('kategori') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        </x-report-filter-modal>

        <x-report-tabs
            label="Bagian laporan penyusutan"
            :tabs="[
                'penyusutan-ringkasan' => [
                    'label' => 'Ringkasan',
                    'description' => 'Ikhtisar nilai penyusutan aset.',
                    'icon' => 'bi-grid-1x2',
                ],
                'penyusutan-rincian' => [
                    'label' => 'Rincian Aset',
                    'description' => 'Perhitungan penyusutan per barang.',
                    'icon' => 'bi-table',
                ],
            ]"
        >
            <x-report-tab-pane
                id="penyusutan-ringkasan"
                title="Ringkasan Penyusutan"
                description="Nilai utama penyusutan fiskal aset untuk tahun yang dipilih."
                active
            >
        <div class="report-stat-grid">
            <x-stat-card icon="bi-box-seam" label="Total Aset" :value="number_format($totalAset, 0, ',', '.')" plain />
            <x-stat-card icon="bi-cash-stack" label="Total Harga Perolehan" :value="'Rp '.number_format($totalHargaPerolehan, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-graph-down" label="Penyusutan Tahun {{ $tahun }}" :value="'Rp '.number_format($totalPenyusutanTahunIni, 0, ',', '.')" compact plain accent />
            <x-stat-card icon="bi-wallet2" label="Total Nilai Buku" :value="'Rp '.number_format($totalNilaiBuku, 0, ',', '.')" compact plain />
        </div>
            </x-report-tab-pane>

            <x-report-tab-pane
                id="penyusutan-rincian"
                :title="'Rincian Penyusutan Tahun '.$tahun"
                description="Perhitungan harga perolehan, akumulasi penyusutan, dan nilai buku tiap aset."
            >
            <div class="table-responsive report-tab-table">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th>Golongan</th>
                            <th>Metode</th>
                            <th class="text-end">Harga Perolehan</th>
                            <th class="text-end">Akumulasi Awal Tahun</th>
                            <th class="text-end">Penyusutan Tahun Ini</th>
                            <th class="text-end">Akumulasi Akhir Tahun</th>
                            <th class="text-end">Nilai Buku Akhir Tahun</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($barangs as $barang)
                        @php $r = $rincian->get($barang->id); @endphp
                        <tr>
                            <td><strong>{{ $barang->kode_barang }}</strong></td>
                            <td>{{ $barang->nama_barang }}</td>
                            <td>{{ config('inventaris.kategori_label_singkat')[$barang->kategori] ?? $barang->kategori }}</td>
                            <td>{{ \App\Support\PenyusutanCalculator::namaMetode($r['metode']) }}</td>
                            <td class="text-end">Rp {{ number_format($barang->harga_perolehan, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($r['akumulasi_awal_tahun'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($r['penyusutan_tahun_ini'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($r['akumulasi_akhir_tahun'], 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($r['nilai_buku_akhir_tahun'], 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <x-empty-row :colspan="9">Tidak ada aset yang cocok dengan filter untuk tahun {{ $tahun }}.</x-empty-row>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td colspan="4">Total</td>
                            <td class="text-end">Rp {{ number_format($totalHargaPerolehan, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($totalAkumulasiAwalTahun, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($totalPenyusutanTahunIni, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($totalAkumulasiPenyusutan, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($totalNilaiBuku, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <x-pagination-footer :paginator="$barangs" class="d-print-none" />
            </x-report-tab-pane>
        </x-report-tabs>

</x-app-page>
@endsection
