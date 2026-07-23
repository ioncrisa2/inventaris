@extends('layouts.app')

@section('title', $barang->exists ? 'Edit Barang' : 'Tambah Barang')

@section('content')
<x-form-page
    :title="$barang->exists ? 'Edit Barang' : 'Tambah Barang'"
    :action="$barang->exists ? route('barang.update', $barang) : route('barang.store')"
    :method="$barang->exists ? 'PUT' : 'POST'"
    :cancel-route="route('barang.index')"
    :submit-label="$barang->exists ? 'Simpan Perubahan' : 'Simpan Barang'"
    class="is-wide"
>
    <x-form.section title="Data Inventaris" description="Semua field bertanda bintang wajib diisi." />

    @if($barang->exists)
    <div class="alert alert-secondary d-flex align-items-center gap-2">
        <i class="bi bi-upc-scan"></i>
        <div>Kode Barang: <strong>{{ $barang->kode_barang }}</strong> <span class="text-body-secondary">(dibuat otomatis, tidak bisa diubah)</span></div>
    </div>
    @else
    <div class="alert alert-secondary d-flex align-items-center gap-2">
        <i class="bi bi-upc-scan"></i>
        <div>Kode barang akan dibuat otomatis oleh sistem saat data ini disimpan.</div>
    </div>
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <x-form.input name="nama_barang" label="Nama Barang" :value="$barang->nama_barang" required autofocus maxlength="255" />
        </div>

        <div class="col-md-6">
            <x-form.select
                name="kategori"
                label="Golongan"
                :options="collect(config('inventaris.kategori'))->mapWithKeys(fn ($kategori) => [$kategori => $kategori])"
                :value="$barang->kategori"
                required
                placeholder="Pilih golongan"
            />
        </div>

        <div class="col-md-6">
            <x-form.select
                name="unit_kerja_id"
                label="Unit Kerja"
                :options="$unitKerjas->pluck('nama_unit', 'id')"
                :value="$barang->unit_kerja_id"
                required
                placeholder="Pilih unit kerja"
            />
        </div>

        <div class="col-md-6">
            <x-form.input
                name="tanggal_perolehan"
                label="Tanggal Perolehan"
                type="date"
                :value="$barang->tanggal_perolehan?->format('Y-m-d')"
                required
                :max="now()->toDateString()"
            />
        </div>

        <div class="col-md-6">
            <x-form.money-input name="harga_perolehan" label="Harga Perolehan" :value="$barang->harga_perolehan" required />
        </div>
    </div>

    <x-form.section title="Foto Barang" description="Opsional. Foto sampul akan tampil di halaman detail barang." />

    <div class="row g-3">
        <div class="col-md-6">
            @if($barang->exists && $barang->foto_sampul)
            <div class="current-image mb-2">
                <x-image-preview :src="\Illuminate\Support\Facades\Storage::url($barang->foto_sampul)" alt="Foto sampul saat ini" size="medium" />
                <div class="form-text">Foto saat ini — unggah file baru untuk mengganti.</div>
            </div>
            @endif
            <x-form.file name="foto_sampul" label="Foto Sampul" accept="image/*" help="JPG/PNG/WEBP, maks. 2MB." />
        </div>

        @unless($barang->exists)
        <div class="col-md-6">
            <x-form.file name="foto_pendukung" label="Foto Pendukung" accept="image/*" multiple help="Opsional, bisa pilih beberapa file sekaligus. JPG/PNG/WEBP, maks. 2MB per file." />
        </div>
        @endunless
    </div>

    <x-form.section title="Dokumen Pendukung" description="Opsional. Nota pembelian, kartu garansi, atau dokumen pendukung lain." />

    @if($errors->has('dokumen.*.jenis_dokumen') || $errors->has('dokumen.*.dokumen'))
    <div class="alert alert-danger">Ada masalah pada salah satu baris dokumen (jenis atau file tidak valid). Silakan periksa kembali bagian Dokumen Pendukung di bawah.</div>
    @endif

    @if($barang->exists && $barang->dokumen_count > 0)
    <p class="text-body-secondary small">
        <i class="bi bi-info-circle"></i>
        Barang ini sudah punya {{ $barang->dokumen_count }} dokumen tersimpan — lihat/kelola di <a href="{{ route('barang.show', $barang) }}">halaman detail</a>. Baris di bawah ini untuk menambah dokumen baru.
    </p>
    @endif

    <x-form.dokumen-repeater name="dokumen" :jenis-options="config('inventaris.jenis_dokumen')" />
</x-form-page>
@endsection
