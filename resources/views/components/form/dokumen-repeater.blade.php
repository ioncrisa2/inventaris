@props(['name' => 'dokumen', 'jenisOptions' => [], 'accept' => '.pdf,image/*', 'help' => 'PDF/JPG/PNG, maks. 5MB.'])

{{--
    Repeater generik untuk upload dokumen pendukung (jenis + file per baris).
    Baris ditambah/dihapus lewat JS tanpa reload, indeksnya selalu naik
    (tidak dipakai ulang) supaya tidak ada tabrakan nama field antar baris.
    JS di bawah data-driven (baca data-repeater-target/-template dari tombol
    tambah) supaya aman dipakai lebih dari sekali dalam satu halaman.
--}}
<p class="form-text mb-3">{{ $help }}</p>

<div id="{{ $name }}-repeater-rows" data-repeater-rows></div>

<button
    type="button"
    class="btn btn-outline-primary btn-sm"
    data-repeater-add
    data-repeater-target="{{ $name }}-repeater-rows"
    data-repeater-template="{{ $name }}-repeater-template"
>
    <i class="bi bi-plus-circle"></i>
    Tambah Dokumen
</button>

<template id="{{ $name }}-repeater-template">
    <div class="row g-3 align-items-end dokumen-repeater-row mb-3 pb-3 border-bottom">
        <div class="col-md-5">
            <label class="form-label">Jenis Dokumen</label>
            <select name="{{ $name }}[__INDEX__][jenis_dokumen]" class="form-select">
                <option value="">Pilih jenis dokumen</option>
                @foreach($jenisOptions as $jenis)
                <option value="{{ $jenis }}">{{ $jenis }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">File Dokumen</label>
            <div class="file-picker" data-file-picker>
                <input
                    type="file"
                    id="{{ $name }}-file-__INDEX__"
                    name="{{ $name }}[__INDEX__][dokumen]"
                    class="file-picker__input"
                    accept="{{ $accept }}">
                <label class="btn btn-light file-picker__button" for="{{ $name }}-file-__INDEX__">Pilih file</label>
                <span class="file-picker__status" data-file-picker-status>Belum ada file dipilih</span>
            </div>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger" data-repeater-remove aria-label="Hapus baris dokumen">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
</template>

@once
@endonce
