@extends('layouts.app')

@section('title', 'Unit Kerja')

@php
    $createHasError = old('_modal') === 'createUnitKerjaModal' && $errors->has('nama_unit');
    $editHasError = old('_modal') === 'editUnitKerjaModal' && $errors->has('nama_unit');
    $createKodeHasError = old('_modal') === 'createUnitKerjaModal' && $errors->has('kode');
    $editKodeHasError = old('_modal') === 'editUnitKerjaModal' && $errors->has('kode');
    $showTenant = auth()->user()->isSuperAdmin();
    $canUpdate = auth()->user()->can('unit-kerja.update');
    $canDelete = auth()->user()->can('unit-kerja.delete');
    $showActions = $canUpdate || $canDelete;
@endphp

@section('content')
    <x-app-page long-footer>
        <x-page-header title="Unit Kerja">
            <x-slot:actions>
                @can('unit-kerja.create')
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUnitKerjaModal">
                        <i class="bi bi-plus-circle"></i>
                        Tambah Unit Kerja
                    </button>
                @endcan
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table :paginator="$unitKerja">
            <x-slot:toolbar>
                <x-filter-form :action="route('unit-kerja.index')" :reset-route="route('unit-kerja.index')" :has-filters="request()->hasAny(['search', 'koperasi_id'])" submit-label="Cari"
                    submit-icon="bi-search">
                    <div class="col-12 col-sm-auto">
                        <input type="text" name="search" class="form-control" value="{{ request('search') }}"
                            placeholder="Cari unit kerja...">
                    </div>
                    <x-tenant-filter :koperasis="$koperasis" :selected="$selectedKoperasiId" id="unit_kerja_koperasi_id" />
                </x-filter-form>
            </x-slot:toolbar>

            <x-slot:bulkActions>
                @can('unit-kerja.delete')
                    <x-bulk-action-bar id="unit-kerja" noun="unit kerja" :delete-action="route('unit-kerja.bulk-destroy')"
                        delete-message="Unit kerja terpilih akan dihapus permanen." />
                @endcan
            </x-slot:bulkActions>

            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        @can('unit-kerja.delete')
                            <th class="selection-column">
                                <x-table-checkbox group="unit-kerja" label="Pilih semua unit kerja di halaman ini" select-all />
                            </th>
                        @endcan
                        <th>Nama Unit Kerja</th>
                        @if($showTenant)
                            <th>Koperasi</th>
                        @endif
                        <th class="table-col-width-100">Kode</th>
                        <th class="text-end table-col-width-120">Jumlah Karyawan</th>
                        <th class="text-end table-col-width-120">Jumlah Barang</th>
                        @if($showActions)
                            <th class="table-col-width-100">Aksi</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($unitKerja as $data)
                        @php
                            $dependensi = collect([
                                $data->karyawan_count ? $data->karyawan_count . ' karyawan' : null,
                                $data->barang_count ? $data->barang_count . ' barang' : null,
                                $data->user_count ? $data->user_count . ' pengguna' : null,
                            ])->filter()->values();
                            $blockedMessage = $dependensi->isNotEmpty()
                                ? 'Unit "' . $data->nama_unit . '" masih dipakai oleh ' . $dependensi->join(', ', ' dan ') . '. Pindahkan data terkait terlebih dahulu.'
                                : null;
                        @endphp
                        <tr>
                            @can('unit-kerja.delete')
                                <td class="selection-column">
                                    <x-table-checkbox group="unit-kerja" :value="$data->id" :label="'Pilih ' . $data->nama_unit"
                                        :data-bulk-blocked-message="$blockedMessage" />
                                </td>
                            @endcan
                            <td><strong>{{ $data->nama_unit }}</strong></td>
                            @if($showTenant)
                                <td>{{ $data->koperasi?->nama ?? 'Tanpa koperasi' }}</td>
                            @endif
                            <td>{{ $data->kode ?: '—' }}</td>
                            <td class="text-end">{{ $data->karyawan_count }}</td>
                            <td class="text-end">{{ $data->barang_count }}</td>
                            @if($showActions)
                            <td>
                                <div class="table-actions">
                                    @can('update', $data)
                                    <button type="button" class="btn btn-sm btn-action btn-action-neutral" title="Edit"
                                        aria-label="Edit {{ $data->nama_unit }}"
                                        data-bs-toggle="modal" data-bs-target="#editUnitKerjaModal"
                                        data-edit-url="{{ route('unit-kerja.update', $data->id) }}"
                                        data-id="{{ $data->id }}" data-nama-unit="{{ $data->nama_unit }}"
                                        data-kode="{{ $data->kode }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    @endcan

                                    @can('delete', $data)
                                    <x-delete-button :url="route('unit-kerja.destroy', $data->id)"
                                        :message="'Hapus unit kerja &quot;' . $data->nama_unit . '&quot;? Tindakan ini tidak dapat dibatalkan.'"
                                        :blocked-message="$blockedMessage" :label="'Hapus ' . $data->nama_unit" />
                                    @endcan
                                </div>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <x-empty-row :colspan="4 + ($showTenant ? 1 : 0) + ($canDelete ? 1 : 0) + ($showActions ? 1 : 0)">
                            @if(request()->hasAny(['search', 'koperasi_id']))
                                Tidak ada unit kerja yang cocok dengan filter.
                                <a href="{{ route('unit-kerja.index') }}">Hapus filter</a>.
                            @else
                                Data unit kerja belum tersedia.
                            @endif
                        </x-empty-row>
                    @endforelse
                </tbody>
            </table>
        </x-data-table>
    </x-app-page>

    @can('unit-kerja.create')
    <x-modal-form id="createUnitKerjaModal" title="Tambah Unit Kerja" :action="route('unit-kerja.store')"
        :data-auto-show-modal="$errors->any() && old('_modal') === 'createUnitKerjaModal'">
        <input type="hidden" name="_modal" value="createUnitKerjaModal">

        <label for="create_nama_unit" class="form-label">Nama Unit Kerja <span class="text-danger">*</span></label>
        <input type="text" name="nama_unit" id="create_nama_unit"
            class="form-control {{ $createHasError ? 'is-invalid' : '' }}"
            value="{{ old('_modal') === 'createUnitKerjaModal' ? old('nama_unit') : '' }}"
            placeholder="Contoh: IT, Keuangan, SDM, Operasional" required>
        @if ($createHasError)
            <div class="invalid-feedback d-block">{{ $errors->first('nama_unit') }}</div>
        @endif
        <div class="form-text">Gunakan nama unit kerja yang singkat dan jelas.</div>

        <div class="mt-3">
            <label for="create_kode" class="form-label">Kode Unit</label>
            <input type="text" name="kode" id="create_kode"
                class="form-control {{ $createKodeHasError ? 'is-invalid' : '' }}"
                value="{{ old('_modal') === 'createUnitKerjaModal' ? old('kode') : '' }}" maxlength="10"
                placeholder="Contoh: IT, KEU, SDM">
            @if ($createKodeHasError)
                <div class="invalid-feedback d-block">{{ $errors->first('kode') }}</div>
            @endif
            <div class="form-text">Dipakai sebagai token {UNIT} pada format kode barang otomatis (lihat Pengaturan
                Aplikasi).</div>
        </div>
    </x-modal-form>
    @endcan

    @can('unit-kerja.update')
    <x-modal-form id="editUnitKerjaModal" title="Edit Unit Kerja" form-id="editUnitKerjaForm" method="PUT"
        :data-auto-show-modal="$errors->any() && old('_modal') === 'editUnitKerjaModal'"
        submit-label="Simpan Perubahan" :action="old('_modal') === 'editUnitKerjaModal' && old('_unit_kerja_id') ? route('unit-kerja.update', old('_unit_kerja_id')) : '#'">
        <input type="hidden" name="_modal" value="editUnitKerjaModal">
        <input type="hidden" name="_unit_kerja_id" id="edit_unit_kerja_id" value="{{ old('_unit_kerja_id') }}">

        <label for="edit_nama_unit" class="form-label">Nama Unit Kerja <span class="text-danger">*</span></label>
        <input type="text" name="nama_unit" id="edit_nama_unit"
            class="form-control {{ $editHasError ? 'is-invalid' : '' }}"
            value="{{ old('_modal') === 'editUnitKerjaModal' ? old('nama_unit') : '' }}" required>
        @if ($editHasError)
            <div class="invalid-feedback d-block">{{ $errors->first('nama_unit') }}</div>
        @endif

        <div class="mt-3">
            <label for="edit_kode" class="form-label">Kode Unit</label>
            <input type="text" name="kode" id="edit_kode"
                class="form-control {{ $editKodeHasError ? 'is-invalid' : '' }}"
                value="{{ old('_modal') === 'editUnitKerjaModal' ? old('kode') : '' }}" maxlength="10"
                placeholder="Contoh: IT, KEU, SDM">
            @if ($editKodeHasError)
                <div class="invalid-feedback d-block">{{ $errors->first('kode') }}</div>
            @endif
            <div class="form-text">Dipakai sebagai token {UNIT} pada format kode barang otomatis (lihat Pengaturan
                Aplikasi).</div>
        </div>
    </x-modal-form>
    @endcan
@endsection
