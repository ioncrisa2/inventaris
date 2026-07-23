@extends('layouts.app')

@section('title', 'Komponen Gaji')

@php
    $modal = old('_modal');
    $createOld = fn ($field, $default = null) => $modal === 'createKomponenGajiModal' ? old($field, $default) : $default;
    $editOld = fn ($field, $default = null) => $modal === 'editKomponenGajiModal' ? old($field, $default) : $default;
    $createErr = fn ($field) => $modal === 'createKomponenGajiModal' && $errors->has($field);
    $editErr = fn ($field) => $modal === 'editKomponenGajiModal' && $errors->has($field);
@endphp

@section('content')
<x-app-page long-footer>
        <x-page-header title="Komponen Gaji" subtitle="Aturan tunjangan dan potongan untuk perhitungan gaji.">
            <x-slot:actions>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createKomponenGajiModal">
                    <i class="bi bi-plus-circle"></i>
                    Tambah Komponen Gaji
                </button>
            </x-slot:actions>
        </x-page-header>

        <x-flash-alert />

        <x-data-table-card :paginator="$komponenGaji">
            <x-slot:toolbar>
                <x-filter-form
                    :action="route('komponen-gaji.index')"
                    :reset-route="route('komponen-gaji.index')"
                    :has-filters="request()->hasAny(['jenis', 'search'])"
                >
                    <div class="col-12 col-sm-auto">
                    <select name="jenis" class="form-select" data-submit-on-change>
                        <option value="">Semua Jenis</option>
                        @foreach(['Tunjangan', 'Potongan'] as $jenis)
                            <option value="{{ $jenis }}" @selected(request('jenis') === $jenis)>{{ $jenis }}</option>
                        @endforeach
                    </select>
                    </div>
                    <div class="col-12 col-sm-auto filter-form__search">
                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        value="{{ request('search') }}"
                        placeholder="Cari nama komponen...">
                    </div>
                </x-filter-form>
            </x-slot:toolbar>

            <x-slot:bulkActions>
                @can('komponen-gaji.delete')
                    <x-bulk-action-bar
                        id="komponen-gaji"
                        noun="komponen gaji"
                        :delete-action="route('komponen-gaji.bulk-destroy')"
                        delete-message="Komponen hanya akan dihapus jika belum digunakan pada transaksi gaji." />
                @endcan
            </x-slot:bulkActions>

                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            @can('komponen-gaji.delete')
                            <th class="selection-column">
                                <x-table-checkbox group="komponen-gaji" label="Pilih semua komponen gaji di halaman ini" select-all />
                            </th>
                            @endcan
                            <th>Nama Komponen</th>
                            <th class="table-col-width-120">Jenis</th>
                            <th>Metode Perhitungan</th>
                            <th class="text-end table-col-width-150">Nilai Default</th>
                            <th class="table-col-width-100">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($komponenGaji as $data)
                        <tr>
                            @can('komponen-gaji.delete')
                            <td class="selection-column">
                                <x-table-checkbox group="komponen-gaji" :value="$data->id" :label="'Pilih '.$data->nama_komponen" />
                            </td>
                            @endcan
                            <td><strong>{{ $data->nama_komponen }}</strong></td>
                            <td>
                                <x-badge :color="$data->jenis === 'Tunjangan' ? 'text-bg-success' : 'text-bg-secondary'">{{ $data->jenis }}</x-badge>
                            </td>
                            <td>
                                {{ \App\Models\KomponenGaji::METODE_PERHITUNGAN[$data->metode_perhitungan] ?? $data->metode_perhitungan }}
                                @if($data->metode_perhitungan === 'persentase')
                                    <span class="text-body-secondary small">(dasar: gaji pokok)</span>
                                @elseif($data->metode_perhitungan === 'per_kehadiran')
                                    <span class="text-body-secondary small">(dikali hari hadir bulan berjalan)</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if($data->metode_perhitungan === 'persentase')
                                    {{ rtrim(rtrim($data->nilai_default, '0'), '.') }}%
                                @elseif($data->metode_perhitungan === 'per_kehadiran')
                                    Rp {{ number_format($data->nilai_default, 0, ',', '.') }} /hari
                                @else
                                    Rp {{ number_format($data->nilai_default, 0, ',', '.') }}
                                @endif
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-action btn-action-neutral"
                                        title="Edit"
                                        aria-label="Edit {{ $data->nama_komponen }}"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editKomponenGajiModal"
                                        data-edit-url="{{ route('komponen-gaji.update', $data->id) }}"
                                        data-id="{{ $data->id }}"
                                        data-nama-komponen="{{ $data->nama_komponen }}"
                                        data-jenis="{{ $data->jenis }}"
                                        data-metode-perhitungan="{{ $data->metode_perhitungan }}"
                                        data-nilai-default="{{ $data->nilai_default }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>

                                    <x-delete-button
                                        :url="route('komponen-gaji.destroy', $data->id)"
                                        :message="'Hapus komponen gaji &quot;'.$data->nama_komponen.'&quot;? Penghapusan akan ditolak jika komponen sudah dipakai pada transaksi gaji.'"
                                        :label="'Hapus '.$data->nama_komponen"
                                    />
                                </div>
                            </td>
                        </tr>
                        @empty
                        <x-empty-row :colspan="auth()->user()->can('komponen-gaji.delete') ? 6 : 5">Data komponen gaji belum tersedia.</x-empty-row>
                        @endforelse
                    </tbody>
                </table>
        </x-data-table-card>
</x-app-page>

@foreach(['create' => 'createKomponenGajiModal', 'edit' => 'editKomponenGajiModal'] as $mode => $modalId)
<x-modal-form
    :id="$modalId"
    :data-auto-show-modal="$errors->any() && $modal === $modalId"
    :title="$mode === 'create' ? 'Tambah Komponen Gaji' : 'Edit Komponen Gaji'"
    :form-id="$mode.'KomponenGajiForm'"
    :method="$mode === 'edit' ? 'PUT' : 'POST'"
    :submit-label="$mode === 'create' ? 'Simpan' : 'Simpan Perubahan'"
    :action="$mode === 'create'
        ? route('komponen-gaji.store')
        : (old('_modal') === 'editKomponenGajiModal' && old('_komponen_gaji_id') ? route('komponen-gaji.update', old('_komponen_gaji_id')) : '#')"
>
    <input type="hidden" name="_modal" value="{{ $modalId }}">
    @if($mode === 'edit')
        <input type="hidden" name="_komponen_gaji_id" id="edit_komponen_gaji_id" value="{{ old('_komponen_gaji_id') }}">
    @endif

    <div class="row g-3">
                        <div class="col-12">
                            <label for="{{ $mode }}_nama_komponen" class="form-label">Nama Komponen <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="nama_komponen"
                                id="{{ $mode }}_nama_komponen"
                                class="form-control {{ ($mode === 'create' ? $createErr('nama_komponen') : $editErr('nama_komponen')) ? 'is-invalid' : '' }}"
                                value="{{ $mode === 'create' ? $createOld('nama_komponen') : $editOld('nama_komponen') }}"
                                maxlength="255"
                                required
                            >
                            @if($mode === 'create' ? $createErr('nama_komponen') : $editErr('nama_komponen'))
                                <div class="invalid-feedback d-block">{{ $errors->first('nama_komponen') }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label for="{{ $mode }}_jenis" class="form-label">Jenis <span class="text-danger">*</span></label>
                            <select name="jenis" id="{{ $mode }}_jenis" class="form-select {{ ($mode === 'create' ? $createErr('jenis') : $editErr('jenis')) ? 'is-invalid' : '' }}" required>
                                @foreach(['Tunjangan', 'Potongan'] as $jenisOpsi)
                                    <option value="{{ $jenisOpsi }}" @selected(($mode === 'create' ? $createOld('jenis') : $editOld('jenis')) === $jenisOpsi)>{{ $jenisOpsi }}</option>
                                @endforeach
                            </select>
                            @if($mode === 'create' ? $createErr('jenis') : $editErr('jenis'))
                                <div class="invalid-feedback d-block">{{ $errors->first('jenis') }}</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label for="{{ $mode }}_metode_perhitungan" class="form-label">Metode <span class="text-danger">*</span></label>
                            <select
                                name="metode_perhitungan"
                                id="{{ $mode }}_metode_perhitungan"
                                class="form-select {{ ($mode === 'create' ? $createErr('metode_perhitungan') : $editErr('metode_perhitungan')) ? 'is-invalid' : '' }}"
                                required
                                data-component-value-mode="{{ $mode }}"
                            >
                                <option value="nominal_tetap" @selected(($mode === 'create' ? $createOld('metode_perhitungan', 'nominal_tetap') : $editOld('metode_perhitungan', 'nominal_tetap')) === 'nominal_tetap')>Nominal Tetap</option>
                                <option value="persentase" @selected(($mode === 'create' ? $createOld('metode_perhitungan', 'nominal_tetap') : $editOld('metode_perhitungan', 'nominal_tetap')) === 'persentase')>Persentase</option>
                                <option value="per_kehadiran" @selected(($mode === 'create' ? $createOld('metode_perhitungan', 'nominal_tetap') : $editOld('metode_perhitungan', 'nominal_tetap')) === 'per_kehadiran')>Per Kehadiran</option>
                            </select>
                            @if($mode === 'create' ? $createErr('metode_perhitungan') : $editErr('metode_perhitungan'))
                                <div class="invalid-feedback d-block">{{ $errors->first('metode_perhitungan') }}</div>
                            @endif
                        </div>

                        <div class="col-12">
                            <label for="{{ $mode }}_nilai_default" class="form-label">Nilai Default <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text" id="{{ $mode }}_nilai_default_prefix">Rp</span>
                                <input
                                    type="number"
                                    name="nilai_default"
                                    id="{{ $mode }}_nilai_default"
                                    class="form-control {{ ($mode === 'create' ? $createErr('nilai_default') : $editErr('nilai_default')) ? 'is-invalid' : '' }}"
                                    value="{{ $mode === 'create' ? $createOld('nilai_default') : $editOld('nilai_default') }}"
                                    min="0"
                                    step="0.01"
                                    required
                                >
                                <span class="input-group-text d-none" id="{{ $mode }}_nilai_default_suffix">%</span>
                                @if($mode === 'create' ? $createErr('nilai_default') : $editErr('nilai_default'))
                                    <div class="invalid-feedback d-block">{{ $errors->first('nilai_default') }}</div>
                                @endif
                            </div>
                            <div class="form-text" id="{{ $mode }}_nilai_default_help"></div>
                        </div>
                    </div>
</x-modal-form>
@endforeach
@endsection
