@extends('layouts.app')

@section('title', 'Detail Barang - Sistem Inventaris & Kepegawaian')

@section('content')
@php
$kondisi = $barang->kondisiTerakhir?->kondisi ?? 'Belum diperiksa';
@endphp

<x-app-page>
        <x-page-header
            title="{{ $barang->nama_barang }}"
            subtitle="{{ $barang->kode_barang }} — {{ $barang->unitKerja?->nama_unit ?? 'Belum ditentukan' }}"
        >
            <x-slot:actions>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('barang.barcode', $barang) }}" target="_blank">
                        <i class="bi bi-upc-scan"></i>
                        Cetak Barcode
                    </a>
                    <a class="btn btn-outline-secondary" href="{{ route('barang.qr-code', $barang) }}" target="_blank">
                        <i class="bi bi-qr-code"></i>
                        Cetak QR Code
                    </a>
                    <a class="btn btn-primary" href="{{ route('barang.edit', $barang) }}">
                        <i class="bi bi-pencil"></i>
                        Edit Data
                    </a>
                    <a class="btn btn-light" href="{{ route('barang.index') }}">Kembali</a>
                </div>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <div class="row g-4">
            <div class="col-lg-5">
                <x-section-card title="Informasi Barang" class="h-100">
                    <x-slot:actions>
                        <x-badge :color="config('inventaris.kondisi_warna')[$kondisi] ?? 'bg-secondary'">{{ $kondisi }}</x-badge>
                    </x-slot:actions>

                    <x-image-preview
                        :src="$barang->foto_sampul ? \Illuminate\Support\Facades\Storage::url($barang->foto_sampul) : null"
                        alt="Foto {{ $barang->nama_barang }}"
                        class="mb-3"
                    />

                    <x-detail-list class="detail-list--single">
                        <x-detail-item label="Kode Barang" :value="$barang->kode_barang" emphasis />
                        <x-detail-item label="Nama Barang" :value="$barang->nama_barang" />
                        <x-detail-item label="Golongan" :value="$barang->kategori" />
                        <x-detail-item label="Unit Kerja" :value="$barang->unitKerja?->nama_unit ?? 'Belum ditentukan'" />
                        <x-detail-item label="Tanggal Perolehan" :value="$barang->tanggal_perolehan->translatedFormat('d F Y')" />
                        <x-detail-item label="Harga Perolehan" emphasis>Rp {{ number_format($barang->harga_perolehan, 0, ',', '.') }}</x-detail-item>
                    </x-detail-list>
                </x-section-card>
            </div>

            <div class="col-lg-7">
                <x-data-table-card title="Riwayat Kondisi" subtitle="Catatan pemeriksaan dan biaya perbaikan." class="h-100">
                    <x-slot:toolbar>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalCatatKondisi">
                            <i class="bi bi-plus-circle"></i>
                            Catat Kondisi
                        </button>
                    </x-slot:toolbar>
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Kondisi</th>
                                    <th>Keterangan</th>
                                    <th class="text-end">Biaya Perbaikan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($riwayatKondisi as $riwayat)
                                <tr>
                                    <td>{{ $riwayat->tanggal_pemeriksaan->format('d/m/Y') }}</td>
                                    <td><x-badge :color="config('inventaris.kondisi_warna')[$riwayat->kondisi] ?? 'bg-secondary'">{{ $riwayat->kondisi }}</x-badge></td>
                                    <td>{{ $riwayat->keterangan ?: 'Tidak ada keterangan' }}</td>
                                    <td class="text-end">
                                        {{ $riwayat->biaya_perbaikan !== null ? 'Rp '.number_format($riwayat->biaya_perbaikan, 0, ',', '.') : '—' }}
                                    </td>
                                </tr>
                                @empty
                                <x-empty-row :colspan="4">Belum ada riwayat kondisi.</x-empty-row>
                                @endforelse
                            </tbody>
                        </table>
                </x-data-table-card>
            </div>
        </div>

        <div class="row g-4 mt-0">
            <div class="col-12">
                <x-section-card title="Foto Pendukung" subtitle="Dokumentasi visual kondisi dan identitas barang.">
                    <x-slot:actions>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalUploadFoto">
                            <i class="bi bi-plus-circle"></i>
                            Upload Foto
                        </button>
                    </x-slot:actions>
                    <div class="media-gallery">
                        @forelse($barang->fotoPendukung as $foto)
                        <div class="media-gallery__item">
                            <x-image-preview
                                :src="\Illuminate\Support\Facades\Storage::url($foto->path)"
                                alt="Foto pendukung {{ $barang->nama_barang }}"
                                size="square"
                            />
                            @if($foto->keterangan)
                            <div class="small text-body-secondary text-truncate mt-2" title="{{ $foto->keterangan }}">{{ $foto->keterangan }}</div>
                            @endif
                            <x-delete-button
                                :url="route('barang.foto.destroy', [$barang, $foto])"
                                message="Yakin ingin menghapus foto pendukung ini?"
                                label="Hapus foto pendukung"
                                class="mt-1"
                            />
                        </div>
                        @empty
                        <x-empty-state icon="bi-images" title="Belum ada foto" class="w-100">
                            Unggah foto pendukung untuk melengkapi dokumentasi barang.
                        </x-empty-state>
                        @endforelse
                    </div>
                </x-section-card>
            </div>
        </div>

        <div class="row g-4 mt-0">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-3">
                        <span>Dokumen Pendukung</span>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalUploadDokumen">
                            <i class="bi bi-plus-circle"></i>
                            Unggah Dokumen
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Jenis</th>
                                    <th>Nama File</th>
                                    <th>Tanggal Unggah</th>
                                    <th class="text-nowrap table-col-width-120">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($barang->dokumen as $dokumen)
                                <tr>
                                    <td>{{ $dokumen->jenis_dokumen }}</td>
                                    <td>{{ $dokumen->nama_asli }}</td>
                                    <td>{{ $dokumen->created_at->translatedFormat('d F Y') }}</td>
                                    <td class="text-nowrap">
                                        <div class="table-actions">
                                            <a
                                                class="btn btn-sm btn-action btn-action-neutral"
                                                href="{{ route('barang.dokumen.download', [$barang, $dokumen]) }}"
                                                target="_blank"
                                                aria-label="Lihat {{ $dokumen->nama_asli }}"
                                                title="Lihat/Unduh">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <x-delete-button
                                                :url="route('barang.dokumen.destroy', [$barang, $dokumen])"
                                                :message="'Yakin ingin menghapus dokumen &quot;'.$dokumen->nama_asli.'&quot;?'"
                                                :label="'Hapus '.$dokumen->nama_asli"
                                            />
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <x-empty-row :colspan="4">Belum ada dokumen.</x-empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

</x-app-page>

<x-modal-form
    id="modalUploadDokumen"
    title="Unggah Dokumen"
    :action="route('barang.dokumen.store', $barang)"
    submit-label="Unggah"
>
    <div class="mb-3">
        <x-form.select
            name="jenis_dokumen"
            label="Jenis Dokumen"
            :options="collect(config('inventaris.jenis_dokumen'))->mapWithKeys(fn ($jenis) => [$jenis => $jenis])"
            required
            placeholder="Pilih jenis dokumen"
        />
    </div>

    <x-form.file name="dokumen" label="File Dokumen" required accept=".pdf,image/*" help="PDF/JPG/PNG, maks. 5MB." />
</x-modal-form>

<x-modal-form
    id="modalUploadFoto"
    title="Upload Foto Pendukung"
    :action="route('barang.foto.store', $barang)"
    submit-label="Unggah"
>
    <x-form.file name="foto" label="File Foto" required accept="image/*" help="JPG/PNG/WEBP, maks. 2MB." />
    <div class="mt-3">
        <x-form.input name="keterangan" label="Keterangan" maxlength="255" help="Opsional." />
    </div>
</x-modal-form>

<x-modal-form
    id="modalCatatKondisi"
    :data-auto-show-modal="$errors->any()"
    dialog-class="modal-dialog-centered"
    :action="route('barang.kondisi.store', $barang)"
    submit-label="Simpan Pemeriksaan"
>
    <x-slot:header>
        <div>
            <h2 class="modal-title fs-5" id="modalCatatKondisiLabel">Catat Kondisi Barang</h2>
            <div class="text-muted small">{{ $barang->kode_barang }} — {{ $barang->nama_barang }}</div>
        </div>
    </x-slot:header>

    <div class="mb-3">
        <x-form.input
            name="tanggal_pemeriksaan"
            label="Tanggal Pemeriksaan"
            type="date"
            :value="now()->toDateString()"
            required
            :min="$barang->tanggal_perolehan->format('Y-m-d')"
            :max="now()->toDateString()"
        />
    </div>

    <div class="mb-3">
        <x-form.select
            name="kondisi"
            label="Kondisi"
            :options="collect(config('inventaris.kondisi'))->mapWithKeys(fn ($opsi) => [$opsi => $opsi])"
            :value="$kondisi"
            required
        />
    </div>

    <div class="mb-3">
        <label for="keterangan" class="form-label">Keterangan</label>
        <textarea
            name="keterangan"
            id="keterangan"
            class="form-control @error('keterangan') is-invalid @enderror"
            rows="3"
            maxlength="1000"
            placeholder="Contoh: layar berkedip dan perlu pemeriksaan teknisi">{{ old('keterangan') }}</textarea>
        @error('keterangan')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <x-form.money-input name="biaya_perbaikan" label="Biaya Perbaikan" placeholder="Opsional" />
</x-modal-form>
@endsection
