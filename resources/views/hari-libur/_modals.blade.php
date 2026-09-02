@php
    $createHasError = old('_modal') === 'createHariLiburModal' && ($errors->has('tanggal') || $errors->has('keterangan'));
    $editHasError = old('_modal') === 'editHariLiburModal' && ($errors->has('tanggal') || $errors->has('keterangan'));
@endphp

@can('hari-libur.create')
<x-modal-form id="createHariLiburModal" title="Tambah Hari Libur" :action="route('hari-libur.store')"
    :data-auto-show-modal="$errors->any() && old('_modal') === 'createHariLiburModal'">
    <input type="hidden" name="_modal" value="createHariLiburModal">

    <x-form.input
        id="create_tanggal"
        name="tanggal"
        label="Tanggal"
        type="date"
        :value="old('_modal') === 'createHariLiburModal' ? old('tanggal') : null"
        required
    />

    <div class="mt-3">
        <label for="create_keterangan" class="form-label">Keterangan <span class="text-danger">*</span></label>
        <input type="text" name="keterangan" id="create_keterangan"
            class="form-control {{ old('_modal') === 'createHariLiburModal' && $errors->has('keterangan') ? 'is-invalid' : '' }}"
            value="{{ old('_modal') === 'createHariLiburModal' ? old('keterangan') : '' }}" maxlength="255"
            placeholder="Contoh: Idul Fitri, HUT RI" required>
        @if (old('_modal') === 'createHariLiburModal' && $errors->has('keterangan'))
            <div class="invalid-feedback d-block">{{ $errors->first('keterangan') }}</div>
        @endif
    </div>
</x-modal-form>

<x-modal-form id="importHariLiburModal" title="Import Hari Libur dari Excel" :action="route('hari-libur.import')" submit-label="Import">
    <div class="settings-callout mb-3">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <div>
            <strong>Format file: kolom Tanggal &amp; Keterangan.</strong>
            <p class="mb-0">
                Tanggal yang sudah ada di database tidak akan diubah — cuma tanggal baru yang ditambahkan.
                <a href="{{ route('hari-libur.template') }}">Unduh contoh template</a>.
            </p>
        </div>
    </div>

    <x-form.file name="file" label="File Excel/CSV" policy="calendar_import" required />
</x-modal-form>
@endcan

@can('hari-libur.update')
<x-modal-form id="editHariLiburModal" title="Edit Hari Libur" form-id="editHariLiburForm" method="PUT"
    :data-auto-show-modal="$errors->any() && old('_modal') === 'editHariLiburModal'"
    submit-label="Simpan Perubahan" :action="old('_modal') === 'editHariLiburModal' && old('_hari_libur_id') ? route('hari-libur.update', old('_hari_libur_id')) : '#'">
    <input type="hidden" name="_modal" value="editHariLiburModal">
    <input type="hidden" name="_hari_libur_id" id="edit_hari_libur_id" value="{{ old('_hari_libur_id') }}">

    <x-form.input
        id="edit_tanggal"
        name="tanggal"
        label="Tanggal"
        type="date"
        :value="old('_modal') === 'editHariLiburModal' ? old('tanggal') : null"
        required
    />

    <div class="mt-3">
        <label for="edit_keterangan" class="form-label">Keterangan <span class="text-danger">*</span></label>
        <input type="text" name="keterangan" id="edit_keterangan"
            class="form-control {{ old('_modal') === 'editHariLiburModal' && $errors->has('keterangan') ? 'is-invalid' : '' }}"
            value="{{ old('_modal') === 'editHariLiburModal' ? old('keterangan') : '' }}" maxlength="255" required>
        @if (old('_modal') === 'editHariLiburModal' && $errors->has('keterangan'))
            <div class="invalid-feedback d-block">{{ $errors->first('keterangan') }}</div>
        @endif
    </div>
</x-modal-form>
@endcan
