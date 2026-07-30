@extends('layouts.app')

@section('title', 'Laporan Inventaris - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        @php
            $inventarisActiveFilterCount = collect(request()->only([
                'unit_kerja_id',
                'kategori',
                'tanggal_awal',
                'tanggal_akhir',
            ]))->filter(fn ($value) => filled($value))->count();

            $inventarisSelectedUnit = request()->filled('unit_kerja_id')
                ? ($unitKerjas->firstWhere('id', (int) request('unit_kerja_id'))?->nama_unit ?? 'Unit tidak tersedia')
                : 'Semua unit kerja';

            $inventarisPeriod = match (true) {
                request()->filled('tanggal_awal') && request()->filled('tanggal_akhir') => \Illuminate\Support\Carbon::parse(request('tanggal_awal'))->format('d/m/Y').' s.d. '.\Illuminate\Support\Carbon::parse(request('tanggal_akhir'))->format('d/m/Y'),
                request()->filled('tanggal_awal') => 'Mulai '.\Illuminate\Support\Carbon::parse(request('tanggal_awal'))->format('d/m/Y'),
                request()->filled('tanggal_akhir') => 'Sampai '.\Illuminate\Support\Carbon::parse(request('tanggal_akhir'))->format('d/m/Y'),
                default => 'Semua tanggal',
            };
        @endphp

        <x-page-header title="Laporan Inventaris" subtitle="Nilai aset dan kondisi terakhir sesuai filter.">
            <x-slot:actions>
                <div class="d-flex flex-wrap gap-2 d-print-none">
                    <x-report-filter-button
                        target="inventarisReportFilter"
                        :active-count="$inventarisActiveFilterCount"
                    />
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

        <x-report-filter-summary
            :items="[
                ['label' => 'Unit', 'value' => $inventarisSelectedUnit],
                ['label' => 'Golongan', 'value' => request('kategori') ?: 'Semua golongan'],
                ['label' => 'Periode', 'value' => $inventarisPeriod],
            ]"
        />

        <x-report-filter-modal
            id="inventarisReportFilter"
            title="Filter Laporan Inventaris"
            :action="route('laporan.inventaris')"
            :reset-url="route('laporan.inventaris')"
            :has-filters="$inventarisActiveFilterCount > 0"
            :has-errors="$errors->any()"
        >
            <div class="col-md-6">
                <label class="form-label" for="inventaris_unit_kerja_id">Unit Kerja</label>
                <select class="form-select @error('unit_kerja_id') is-invalid @enderror" id="inventaris_unit_kerja_id" name="unit_kerja_id">
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
                <label class="form-label" for="inventaris_kategori">Golongan</label>
                <select class="form-select @error('kategori') is-invalid @enderror" id="inventaris_kategori" name="kategori">
                    <option value="">Semua golongan</option>
                    @foreach(config('inventaris.kategori') as $kategori)
                        <option value="{{ $kategori }}" @selected(old('kategori', request('kategori')) === $kategori)>
                            {{ $kategori }}
                        </option>
                    @endforeach
                </select>
                @error('kategori') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <x-form.input name="tanggal_awal" label="Dari Tanggal" type="date" :value="old('tanggal_awal', request('tanggal_awal'))" />
            </div>

            <div class="col-md-6">
                <x-form.input name="tanggal_akhir" label="Sampai Tanggal" type="date" :value="old('tanggal_akhir', request('tanggal_akhir'))" />
            </div>
        </x-report-filter-modal>

        <x-report-tabs
            label="Bagian laporan inventaris"
            :tabs="[
                'inventaris-ringkasan' => [
                    'label' => 'Ringkasan',
                    'description' => 'Ikhtisar aset dan kondisi.',
                    'icon' => 'bi-grid-1x2',
                ],
                'inventaris-rekap' => [
                    'label' => 'Rekap Golongan',
                    'description' => 'Jumlah dan nilai per golongan.',
                    'icon' => 'bi-bar-chart',
                ],
                'inventaris-detail' => [
                    'label' => 'Detail Inventaris',
                    'description' => 'Daftar aset sesuai filter.',
                    'icon' => 'bi-table',
                ],
            ]"
        >
            <x-report-tab-pane
                id="inventaris-ringkasan"
                title="Ringkasan Inventaris"
                description="Nilai utama inventaris berdasarkan filter yang sedang diterapkan."
                active
            >
        <div class="report-stat-grid">
            <x-stat-card icon="bi-box-seam" label="Total Barang" :value="number_format($totalBarang, 0, ',', '.')" plain />
            <x-stat-card icon="bi-cash-stack" label="Total Nilai Perolehan" :value="'Rp '.number_format($totalNilai, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-tools" label="Perlu Perbaikan" :value="number_format($barangPerluPerbaikan, 0, ',', '.')" plain accent />
        </div>
            </x-report-tab-pane>

            <x-report-tab-pane
                id="inventaris-rekap"
                title="Rekapitulasi per Golongan"
                description="Perbandingan jumlah barang dan nilai perolehan pada setiap golongan."
            >
            <div class="table-responsive report-tab-table">
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
            </x-report-tab-pane>

            <x-report-tab-pane
                id="inventaris-detail"
                title="Detail Inventaris"
                description="Daftar lengkap barang inventaris yang cocok dengan filter."
            >
            <div class="table-responsive report-tab-table">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Nama Barang</th>
                            <th>Jenis Barang</th>
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
                            <td>{{ $barang->jenis_barang ?? '—' }}</td>
                            <td>{{ $barang->kategori }}</td>
                            <td>{{ $barang->unitKerja?->nama_unit ?? '—' }}</td>
                            <td>{{ $barang->tanggal_perolehan->format('d/m/Y') }}</td>
                            <td><x-badge :color="config('inventaris.kondisi_warna')[$kondisi] ?? 'bg-secondary'">{{ $kondisi }}</x-badge></td>
                            <td class="text-end">Rp {{ number_format($barang->harga_perolehan, 0, ',', '.') }}</td>
                        </tr>
                        @empty
                        <x-empty-row :colspan="8">Tidak ada inventaris yang cocok dengan filter.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <x-pagination-footer :paginator="$barangs" class="d-print-none" />
            </x-report-tab-pane>
        </x-report-tabs>

</x-app-page>
@endsection
