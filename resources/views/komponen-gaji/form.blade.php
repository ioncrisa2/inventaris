@extends('layouts.app')

@section('title', $komponenGaji->exists ? 'Edit Komponen Gaji' : 'Tambah Komponen Gaji')

@php
    $metodeTerpilih = old('metode_perhitungan', $komponenGaji->metode_perhitungan ?? 'nominal_tetap');
    $nilaiDiisiSaatTransaksi = in_array($metodeTerpilih, \App\Models\KomponenGaji::METODE_INPUT_TRANSAKSI, true);
@endphp

@section('content')
<x-form-page
    :title="$komponenGaji->exists ? 'Edit Komponen Gaji' : 'Tambah Komponen Gaji'"
    subtitle="Tentukan aturan tunjangan atau potongan. Nilai transaksi dapat berasal dari master, perhitungan, atau input petugas saat penggajian."
    :action="$komponenGaji->exists ? route('komponen-gaji.update', $komponenGaji) : route('komponen-gaji.store')"
    :method="$komponenGaji->exists ? 'PUT' : 'POST'"
    :cancel-route="route('komponen-gaji.index')"
    :submit-label="$komponenGaji->exists ? 'Simpan Perubahan' : 'Simpan Komponen'"
>
    <div data-component-salary-form>
        <x-form.section title="Aturan Komponen" description="Semua field bertanda bintang wajib diisi." />

        <div class="row g-3">
            <div class="col-12">
                <x-form.input
                    name="nama_komponen"
                    label="Nama Komponen"
                    :value="$komponenGaji->nama_komponen"
                    required
                    autofocus
                    maxlength="255"
                    help="Gunakan nama umum, misalnya Tunjangan Beras. Perbedaan penerima dapat dicatat melalui metode list."
                />
            </div>

            <div class="col-md-6">
                <x-form.select
                    name="jenis"
                    label="Jenis"
                    :options="collect(['Tunjangan', 'Potongan'])->mapWithKeys(fn ($jenis) => [$jenis => $jenis])"
                    :value="$komponenGaji->jenis ?? 'Tunjangan'"
                    required
                />
            </div>

            <div class="col-md-6">
                <x-form.select
                    name="metode_perhitungan"
                    label="Metode Perhitungan"
                    :options="collect(\App\Models\KomponenGaji::METODE_PERHITUNGAN)"
                    :value="$metodeTerpilih"
                    required
                    data-component-calculation-method
                />
            </div>

            <div class="col-12 {{ $nilaiDiisiSaatTransaksi ? 'd-none' : '' }}" data-component-default-value>
                <label for="nilai_default" class="form-label">Nilai Default <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text" data-component-default-prefix>Rp</span>
                    <input
                        type="number"
                        name="nilai_default"
                        id="nilai_default"
                        class="form-control @error('nilai_default') is-invalid @enderror"
                        value="{{ old('nilai_default', $komponenGaji->nilai_default) }}"
                        min="0"
                        step="0.01"
                        @required(!$nilaiDiisiSaatTransaksi)
                        @disabled($nilaiDiisiSaatTransaksi)
                        data-component-default-input
                    >
                    <span class="input-group-text d-none" data-component-default-suffix>%</span>
                    @error('nilai_default')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text" data-component-default-help></div>
            </div>

            <div class="col-12 {{ $nilaiDiisiSaatTransaksi ? '' : 'd-none' }}" data-component-transaction-note>
                <div class="alert alert-secondary mb-0 d-flex gap-2 align-items-start">
                    <i class="bi bi-pencil-square" aria-hidden="true"></i>
                    <div data-component-transaction-note-text></div>
                </div>
            </div>
        </div>
    </div>
</x-form-page>
@endsection
