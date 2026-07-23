@extends('layouts.app')

@section('title', $role->exists ? 'Edit Role' : 'Tambah Role')

@section('content')
<x-form-page
    :title="$role->exists ? 'Edit Role' : 'Tambah Role'"
    :action="$role->exists ? route('role.update', $role) : route('role.store')"
    :method="$role->exists ? 'PUT' : 'POST'"
    :cancel-route="route('role.index')"
    :submit-label="$role->exists ? 'Simpan Perubahan' : 'Simpan Role'"
    class="is-wide"
>
    <x-form.section title="Nama Role" />

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <x-form.input name="name" label="Nama Role" :value="$role->name" required autofocus maxlength="255" />
        </div>
    </div>

    <div class="border-bottom pb-3 mb-4">
        <h2>Hak Akses (Permission)</h2>
        <p>Centang menu dan aksi yang boleh diakses oleh role ini. Menu di sidebar otomatis mengikuti pilihan ini.</p>
        @error('permissions') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
    </div>

    @php($dipilih = collect(old('permissions', $selectedPermissions)))

    <div class="row g-3">
        @foreach($permissionGroups as $kunci => $grup)
            <div class="col-md-6 col-lg-4">
                <div class="permission-group border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>{{ $grup['label'] }}</strong>
                        <button
                            type="button"
                            class="btn btn-link btn-sm p-0 permission-toggle-all"
                            data-group="{{ $kunci }}"
                        >
                            Pilih semua
                        </button>
                    </div>
                    @foreach($grup['permissions'] as $nama => $label)
                        <div class="form-check">
                            <input
                                type="checkbox"
                                class="form-check-input permission-checkbox"
                                data-group="{{ $kunci }}"
                                name="permissions[]"
                                id="perm-{{ $nama }}"
                                value="{{ $nama }}"
                                @checked($dipilih->contains($nama))
                            >
                            <label class="form-check-label" for="perm-{{ $nama }}">{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-form-page>
@endsection
