@extends('layouts.app')

@section('title', 'Barang - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
        <x-page-header title="Inventaris Barang" subtitle="Kondisi terakhir dan nilai aset pada setiap unit.">
            <x-slot:actions>
                <a class="btn btn-primary" href="{{ route('barang.create') }}">
                    <i class="bi bi-plus-circle"></i>
                    Tambah Barang
                </a>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table-card :paginator="$barangs">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('barang.index')"
                    :reset-route="route('barang.index')"
                    :has-filters="request()->hasAny(['search', 'unit_kerja_id', 'kategori', 'kondisi', 'kelengkapan'])"
                >
                    <div class="col-12 col-lg-auto">
                        <label class="visually-hidden" for="search">Cari barang</label>
                        <input
                            class="form-control"
                            id="search"
                            name="search"
                            type="search"
                            value="{{ request('search') }}"
                            placeholder="Cari kode, nama, kategori…">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="unit_kerja_id">Unit kerja</label>
                        <select class="form-select" id="unit_kerja_id" name="unit_kerja_id">
                            <option value="">Semua unit kerja</option>
                            @foreach($unitKerjas as $unit)
                            <option value="{{ $unit->id }}" @selected((string) request('unit_kerja_id')===(string) $unit->id)>
                                {{ $unit->nama_unit }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="kategori">Golongan</label>
                        <select class="form-select" id="kategori" name="kategori">
                            <option value="">Semua Golongan</option>
                            @foreach(config('inventaris.kategori') as $kategori)
                            <option value="{{ $kategori }}"
                                @selected(request('kategori')===$kategori)>
                                {{ $kategori }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="kondisi">Kelompok kondisi</label>
                        <select class="form-select" id="kondisi" name="kondisi">
                            <option value="">Semua kondisi</option>
                            @foreach(config('inventaris.kondisi_grup') as $kunci => $grup)
                            <option value="{{ $kunci }}" @selected(request('kondisi') === $kunci)>{{ $grup['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-auto">
                        <label class="visually-hidden" for="kelengkapan">Kelengkapan data</label>
                        <select class="form-select" id="kelengkapan" name="kelengkapan">
                            <option value="">Semua kelengkapan</option>
                            <option value="belum-diperiksa" @selected(request('kelengkapan') === 'belum-diperiksa')>Belum diperiksa</option>
                            <option value="tanpa-foto" @selected(request('kelengkapan') === 'tanpa-foto')>Tanpa foto</option>
                            <option value="tanpa-nota" @selected(request('kelengkapan') === 'tanpa-nota')>Tanpa nota pembelian</option>
                        </select>
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

            <x-slot:bulkActions>
                <x-bulk-action-bar
                    id="barang"
                    noun="barang"
                    :delete-action="auth()->user()->can('barang.delete') ? route('barang.bulk-destroy') : null"
                    delete-message="Barang hanya akan dihapus jika tidak memiliki riwayat kondisi, foto pendukung, atau dokumen.">
                    <x-slot:actions>
                        <form
                            method="POST"
                            action="{{ route('barang.barcode.bulk') }}"
                            target="_blank"
                            data-bulk-form="barang"
                            data-bulk-input-name="barang_ids[]">
                            @csrf
                            <button class="btn btn-sm btn-primary" type="submit">
                                <i class="bi bi-upc-scan"></i>
                                Export Barcode
                            </button>
                        </form>
                    </x-slot:actions>
                </x-bulk-action-bar>
            </x-slot:bulkActions>

                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="selection-column">
                                <x-table-checkbox group="barang" label="Pilih semua barang di halaman ini" select-all />
                            </th>
                            <th class="table-col-width-100">Kode</th>
                            <th>Nama Barang</th>
                            <th class="table-col-width-100">Golongan</th>
                            <th>Unit Kerja</th>
                            <th class="table-col-width-120">Tanggal Perolehan</th>
                            <th class="text-end table-col-width-150">Harga Perolehan</th>
                            <th class="table-col-width-130">Kondisi</th>
                            <th class="text-nowrap table-col-width-130">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($barangs as $barang)
                        @php
                        $kondisi = $barang->kondisiTerakhir?->kondisi ?? 'Belum diperiksa';
                        @endphp
                        <tr>
                            <td class="selection-column">
                                <x-table-checkbox group="barang" :value="$barang->id" :label="'Pilih '.$barang->nama_barang" />
                            </td>
                            <td><strong>{{ $barang->kode_barang }}</strong></td>
                            <td>{{ $barang->nama_barang }}</td>
                            <td title="{{ $barang->kategori }}">{{ config('inventaris.kategori_label_singkat')[$barang->kategori] ?? $barang->kategori }}</td>
                            <td>{{ $barang->unitKerja?->nama_unit ?? 'Belum ditentukan' }}</td>
                            <td>{{ $barang->tanggal_perolehan->format('d/m/Y') }}</td>
                            <td class="text-end">Rp {{ number_format($barang->harga_perolehan, 0, ',', '.') }}</td>
                            <td><x-badge :color="config('inventaris.kondisi_warna')[$kondisi] ?? 'bg-secondary'">{{ $kondisi }}</x-badge></td>
                            <td class="text-nowrap">
                                <div class="table-actions">
                                    <a
                                        class="btn btn-sm btn-action btn-action-neutral"
                                        href="{{ route('barang.show', $barang) }}"
                                        aria-label="Detail {{ $barang->nama_barang }}"
                                        title="Detail">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a
                                        class="btn btn-sm btn-action btn-action-neutral"
                                        href="{{ route('barang.edit', $barang) }}"
                                        aria-label="Edit {{ $barang->nama_barang }}"
                                        title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <x-delete-button
                                        :url="route('barang.destroy', $barang)"
                                        :message="'Hapus barang &quot;'.$barang->nama_barang.'&quot;? Penghapusan akan ditolak jika masih memiliki riwayat kondisi, foto pendukung, atau dokumen.'"
                                        :label="'Hapus '.$barang->nama_barang"
                                    />
                                </div>
                            </td>
                        </tr>
                        @empty
                        <x-empty-row :colspan="9">
                            @if(request()->hasAny(['search', 'unit_kerja_id', 'kategori', 'kondisi', 'kelengkapan']))
                            Tidak ada barang yang cocok dengan filter.
                            <a href="{{ route('barang.index') }}">Hapus filter</a>.
                            @else
                            Data barang belum tersedia.
                            <a href="{{ route('barang.create') }}">Tambah barang pertama</a>.
                            @endif
                        </x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table-card>
</x-app-page>
@endsection
