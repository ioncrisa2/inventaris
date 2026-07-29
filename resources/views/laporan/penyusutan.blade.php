@extends('layouts.app')

@section('title', 'Laporan Penyusutan - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header title="Laporan Penyusutan" subtitle="Rekap penyusutan fiskal aset per tahun — dasar pelaporan SPT.">
            <x-slot:actions>
                <div class="d-flex gap-2 d-print-none">
                    <a class="btn btn-outline-primary" href="{{ route('laporan.penyusutan.cetak', request()->query()) }}" target="_blank" rel="noopener">
                        <i class="bi bi-printer"></i>
                        Cetak
                    </a>
                    <a class="btn btn-outline-success" href="{{ route('laporan.penyusutan.export', request()->query()) }}">
                        <i class="bi bi-file-earmark-excel"></i>
                        Export Excel
                    </a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-filter-card>
                <form action="{{ route('laporan.penyusutan') }}" method="GET" class="row g-3 align-items-end">
                    <div class="col-md-6 col-xl-3">
                        <label class="form-label" for="tahun">Tahun</label>
                        <input
                            type="number"
                            name="tahun"
                            id="tahun"
                            class="form-control @error('tahun') is-invalid @enderror"
                            value="{{ request('tahun', $tahun) }}"
                            min="2000"
                            max="2100"
                            required
                        >
                        @error('tahun') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

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

                    <div class="col-xl-3 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Terapkan</button>
                        @if(request()->hasAny(['unit_kerja_id', 'kategori']))
                        <a class="btn btn-light" href="{{ route('laporan.penyusutan', ['tahun' => $tahun]) }}">Reset</a>
                        @endif
                    </div>
                </form>
        </x-filter-card>

        <div class="report-stat-grid mb-4">
            <x-stat-card icon="bi-box-seam" label="Total Aset" :value="number_format($totalAset, 0, ',', '.')" plain />
            <x-stat-card icon="bi-cash-stack" label="Total Harga Perolehan" :value="'Rp '.number_format($totalHargaPerolehan, 0, ',', '.')" compact plain />
            <x-stat-card icon="bi-graph-down" label="Penyusutan Tahun {{ $tahun }}" :value="'Rp '.number_format($totalPenyusutanTahunIni, 0, ',', '.')" compact plain accent />
            <x-stat-card icon="bi-wallet2" label="Total Nilai Buku" :value="'Rp '.number_format($totalNilaiBuku, 0, ',', '.')" compact plain />
        </div>

        <div class="card">
            <div class="card-header">Rincian Penyusutan Tahun {{ $tahun }}</div>
            <div class="table-responsive">
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
                            <td>{{ $r['metode'] === 'garis_lurus' ? 'Garis Lurus' : $r['metode'] }}</td>
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
        </div>

</x-app-page>
@endsection
