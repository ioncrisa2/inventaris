@extends('layouts.app')

@section('title', $komponenGaji->exists ? 'Edit Komponen Gaji' : 'Tambah Komponen Gaji')

@php
    $metodeTerpilih = old('metode_perhitungan', $komponenGaji->metode_perhitungan ?? 'nominal_tetap');
    $nilaiDiisiSaatTransaksi = in_array($metodeTerpilih, \App\Models\KomponenGaji::METODE_INPUT_TRANSAKSI, true);
    $memakaiDaftarTetap = $metodeTerpilih === \App\Models\KomponenGaji::METODE_DAFTAR_TETAP;
    $rincianTersimpan = $komponenGaji->exists
        ? $komponenGaji->rincian->map(fn ($item) => [
            'keterangan' => $item->keterangan,
            'nominal' => (string) $item->nominal,
        ])->all()
        : [];
    $rincian = collect(old('rincian', $rincianTersimpan));

    if ($rincian->isEmpty()) {
        $rincian = collect([['keterangan' => '', 'nominal' => '']]);
    }

    $nextRincianIndex = ((int) ($rincian->keys()->max() ?? -1)) + 1;
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

            <div class="col-12 {{ $nilaiDiisiSaatTransaksi || $memakaiDaftarTetap ? 'd-none' : '' }}" data-component-default-value>
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
                        @required(!$nilaiDiisiSaatTransaksi && !$memakaiDaftarTetap)
                        @disabled($nilaiDiisiSaatTransaksi || $memakaiDaftarTetap)
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

            <div
                class="col-12 {{ $memakaiDaftarTetap ? '' : 'd-none' }}"
                data-component-fixed-list
                data-next-index="{{ $nextRincianIndex }}"
            >
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <h2 class="h6 mb-1">Daftar Nominal Tetap</h2>
                        <p class="form-text mb-0">Setiap baris menjadi pilihan rincian tetap saat komponen dipakai dalam transaksi gaji.</p>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-component-fixed-list-add>
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        Tambah Rincian
                    </button>
                </div>

                <div class="mt-2" data-component-fixed-list-rows>
                    @foreach($rincian as $rincianIndex => $item)
                        <div class="row g-2 align-items-end py-3 border-bottom" data-component-fixed-list-row>
                            <div class="col-md-6">
                                <label for="rincian_{{ $rincianIndex }}_keterangan" class="form-label">Keterangan <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    name="rincian[{{ $rincianIndex }}][keterangan]"
                                    id="rincian_{{ $rincianIndex }}_keterangan"
                                    class="form-control @error("rincian.{$rincianIndex}.keterangan") is-invalid @enderror"
                                    value="{{ $item['keterangan'] ?? '' }}"
                                    maxlength="255"
                                    placeholder="Contoh: Laki-laki"
                                    @required($memakaiDaftarTetap)
                                    @disabled(!$memakaiDaftarTetap)
                                >
                                @error("rincian.{$rincianIndex}.keterangan")<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-5">
                                <label for="rincian_{{ $rincianIndex }}_nominal" class="form-label">Nominal <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">Rp</span>
                                    <input
                                        type="number"
                                        name="rincian[{{ $rincianIndex }}][nominal]"
                                        id="rincian_{{ $rincianIndex }}_nominal"
                                        class="form-control @error("rincian.{$rincianIndex}.nominal") is-invalid @enderror"
                                        value="{{ $item['nominal'] ?? '' }}"
                                        min="0"
                                        step="0.01"
                                        placeholder="0"
                                        @required($memakaiDaftarTetap)
                                        @disabled(!$memakaiDaftarTetap)
                                    >
                                    @error("rincian.{$rincianIndex}.nominal")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-1 d-grid">
                                <button
                                    type="button"
                                    class="btn btn-outline-danger"
                                    data-component-fixed-list-remove
                                    aria-label="Hapus rincian"
                                    @disabled(!$memakaiDaftarTetap)
                                >
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="form-text mb-0 mt-3 d-none" data-component-fixed-list-empty>
                    Belum ada rincian. Tambahkan minimal satu keterangan dan nominal.
                </p>
                @error('rincian')<div class="invalid-feedback d-block mt-2">{{ $message }}</div>@enderror

                <template data-component-fixed-list-template>
                    <div class="row g-2 align-items-end py-3 border-bottom" data-component-fixed-list-row>
                        <div class="col-md-6">
                            <label for="rincian___INDEX___keterangan" class="form-label">Keterangan <span class="text-danger">*</span></label>
                            <input type="text" name="rincian[__INDEX__][keterangan]" id="rincian___INDEX___keterangan" class="form-control" maxlength="255" placeholder="Contoh: Perempuan" required>
                        </div>
                        <div class="col-md-5">
                            <label for="rincian___INDEX___nominal" class="form-label">Nominal <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" name="rincian[__INDEX__][nominal]" id="rincian___INDEX___nominal" class="form-control" min="0" step="0.01" placeholder="0" required>
                            </div>
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="button" class="btn btn-outline-danger" data-component-fixed-list-remove aria-label="Hapus rincian">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</x-form-page>
@endsection
