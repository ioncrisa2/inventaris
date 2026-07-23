@extends('layouts.app')

@section('title', 'Pengaturan Aplikasi - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page>
    <x-page-header
        title="Pengaturan Aplikasi"
        subtitle="Atur pengalaman penggunaan aplikasi dan standar penomoran inventaris."
    />

    <x-flash-alert />

    <div class="row g-4 settings-layout">
        <aside class="col-lg-3">
            <nav class="card settings-nav" aria-label="Bagian pengaturan">
                <div class="card-body">
                    <div class="settings-nav__label">Pengaturan</div>
                    <a href="#tampilan" class="settings-nav__link">
                        <i class="bi bi-palette" aria-hidden="true"></i>
                        <span>
                            <strong>Tampilan</strong>
                            <small>Menu dan tema warna</small>
                        </span>
                    </a>
                    @can('pengaturan.view')
                    <a href="#penomoran-inventaris" class="settings-nav__link">
                        <i class="bi bi-upc-scan" aria-hidden="true"></i>
                        <span>
                            <strong>Penomoran Inventaris</strong>
                            <small>Pola kode barang baru</small>
                        </span>
                    </a>
                    @endcan
                </div>
            </nav>
        </aside>

        <div class="col-lg-9 d-grid gap-4">
            <x-section-card
                id="tampilan"
                title="Tampilan Aplikasi"
                subtitle="Preferensi ini disimpan pada browser dan hanya berlaku di perangkat ini."
                class="settings-section"
            >
                <fieldset class="settings-fieldset">
                    <legend>Tampilan menu</legend>
                    <p>Pilih posisi navigasi yang paling sesuai dengan cara kerja Anda.</p>

                    <div class="layout-option-group">
                        <label class="layout-option">
                            <input class="visually-hidden" type="radio" name="app-layout" value="sidebar" id="layoutOptionSidebar">
                            <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i>
                            <strong>Sidebar</strong>
                            <small>Menu utama tetap berada di sisi kiri.</small>
                        </label>
                        <label class="layout-option">
                            <input class="visually-hidden" type="radio" name="app-layout" value="topbar" id="layoutOptionTopbar">
                            <i class="bi bi-layout-text-window" aria-hidden="true"></i>
                            <strong>Top Bar</strong>
                            <small>Menu utama ditampilkan secara horizontal.</small>
                        </label>
                    </div>
                </fieldset>

                <hr class="my-4">

                <fieldset class="settings-fieldset">
                    <legend>Tema warna</legend>
                    <p>Pilih tema terang, gelap, atau ikuti pengaturan sistem perangkat.</p>

                    <div class="btn-group color-mode-group" role="group" aria-label="Pilihan tema warna">
                        <input type="radio" class="btn-check" name="color-mode" value="auto" id="colorModeAuto" autocomplete="off">
                        <label class="btn btn-outline-primary" for="colorModeAuto">
                            <i class="bi bi-circle-half" aria-hidden="true"></i>
                            Sistem
                        </label>

                        <input type="radio" class="btn-check" name="color-mode" value="light" id="colorModeLight" autocomplete="off">
                        <label class="btn btn-outline-primary" for="colorModeLight">
                            <i class="bi bi-sun" aria-hidden="true"></i>
                            Terang
                        </label>

                        <input type="radio" class="btn-check" name="color-mode" value="dark" id="colorModeDark" autocomplete="off">
                        <label class="btn btn-outline-primary" for="colorModeDark">
                            <i class="bi bi-moon-stars" aria-hidden="true"></i>
                            Gelap
                        </label>
                    </div>
                </fieldset>

                <div class="settings-auto-save-note" role="status">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    Perubahan tampilan diterapkan dan disimpan otomatis.
                </div>
            </x-section-card>

            @can('pengaturan.view')
            <x-section-card
                id="penomoran-inventaris"
                title="Format Penomoran Inventaris"
                subtitle="Tentukan struktur kode yang dibuat otomatis ketika inventaris baru ditambahkan."
                class="settings-section"
            >
                @can('pengaturan.update')
                <form method="POST" action="{{ route('pengaturan.update') }}" id="inventoryNumberingForm">
                    @csrf
                    @method('PUT')

                    <div class="settings-callout mb-4">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <div>
                            <strong>Hanya berlaku untuk barang baru</strong>
                            <p>Kode inventaris yang sudah tersimpan tidak akan diubah.</p>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="format_kode_barang">Template kode <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control font-monospace @error('format_kode_barang') is-invalid @enderror"
                            id="format_kode_barang"
                            name="format_kode_barang"
                            value="{{ old('format_kode_barang', $formatKodeBarang) }}"
                            maxlength="255"
                            aria-describedby="formatKodeHelp formatKodeError"
                            required
                        >
                        @error('format_kode_barang')
                            <div class="invalid-feedback" id="formatKodeError">{{ $message }}</div>
                        @enderror
                        <div class="form-text" id="formatKodeHelp">Gunakan minimal token <code>{URUT}</code> untuk menjaga keunikan kode.</div>
                    </div>

                    <div class="mb-4">
                        <span class="form-label d-block">Template cepat</span>
                        <div class="settings-preset-list" aria-label="Pilihan template cepat">
                            <button type="button" class="btn btn-sm btn-light" data-number-template="INV-{TAHUN}-{URUT}">Sederhana</button>
                            <button type="button" class="btn btn-sm btn-light" data-number-template="{UNIT}-{KATEGORI}-{TAHUN}-{URUT}">Per unit &amp; golongan</button>
                            <button type="button" class="btn btn-sm btn-light" data-number-template="{UNIT}/{TAHUN}/{BULAN}/{URUT}">Arsip bulanan</button>
                        </div>
                    </div>

                    <div class="row g-4 align-items-start">
                        <div class="col-md-8">
                            <span class="form-label d-block">Token tersedia</span>
                            <div class="token-list" aria-label="Token format kode barang">
                                @foreach([
                                    '{UNIT}' => 'Kode unit kerja',
                                    '{KATEGORI}' => 'Kode golongan',
                                    '{TAHUN}' => 'Tahun 4 digit',
                                    '{BULAN}' => 'Bulan 2 digit',
                                    '{URUT}' => 'Nomor urut',
                                ] as $token => $description)
                                <button
                                    type="button"
                                    class="token-button"
                                    data-number-token="{{ $token }}"
                                    title="Sisipkan {{ $description }}"
                                >{{ $token }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="digit_nomor_urut">Jumlah digit nomor urut</label>
                            <select
                                class="form-select @error('digit_nomor_urut') is-invalid @enderror"
                                id="digit_nomor_urut"
                                name="digit_nomor_urut"
                                required
                            >
                                @for($digit = \App\Services\KodeBarangGenerator::MIN_SEQUENCE_DIGITS; $digit <= \App\Services\KodeBarangGenerator::MAX_SEQUENCE_DIGITS; $digit++)
                                    <option value="{{ $digit }}" @selected((int) old('digit_nomor_urut', $digitNomorUrut) === $digit)>{{ $digit }} digit</option>
                                @endfor
                            </select>
                            @error('digit_nomor_urut')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="number-preview mt-4" aria-live="polite">
                        <span>Pratinjau kode berikutnya</span>
                        <code id="inventoryNumberPreview"
                            data-year="{{ now()->format('Y') }}"
                            data-month="{{ now()->format('m') }}">{{ $contohKodeBarang }}</code>
                    </div>

                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 pt-4 border-top">
                        <a href="{{ route('dashboard') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save" aria-hidden="true"></i>
                            Simpan Penomoran
                        </button>
                    </div>
                </form>
                @else
                <div class="settings-callout">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <div>
                        <strong>Akses hanya-baca</strong>
                        <p>Anda dapat melihat format, tetapi tidak memiliki izin untuk mengubahnya.</p>
                    </div>
                </div>
                <x-detail-list class="detail-list--single mt-3">
                    <x-detail-item label="Template" :value="$formatKodeBarang" emphasis />
                    <x-detail-item label="Jumlah digit urut" :value="$digitNomorUrut.' digit'" />
                    <x-detail-item label="Contoh kode" :value="$contohKodeBarang" emphasis />
                </x-detail-list>
                @endcan
            </x-section-card>
            @endcan
        </div>
    </div>
</x-app-page>
@endsection

