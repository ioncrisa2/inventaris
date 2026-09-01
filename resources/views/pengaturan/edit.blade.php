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
                    @can('pengaturan.view')
                    <a href="#identitas" class="settings-nav__link">
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <span>
                            <strong>Identitas Koperasi</strong>
                            <small>Nama, alamat, dan logo</small>
                        </span>
                    </a>
                    <a href="#format-slip-gaji" class="settings-nav__link">
                        <i class="bi bi-receipt" aria-hidden="true"></i>
                        <span>
                            <strong>Format Slip Gaji</strong>
                            <small>Susunan blok dan cetak F4</small>
                        </span>
                    </a>
                    <a href="#penomoran-inventaris" class="settings-nav__link">
                        <i class="bi bi-upc-scan" aria-hidden="true"></i>
                        <span>
                            <strong>Penomoran Inventaris</strong>
                            <small>Pola kode barang baru</small>
                        </span>
                    </a>
                    <a href="#hari-operasional" class="settings-nav__link">
                        <i class="bi bi-calendar-week" aria-hidden="true"></i>
                        <span>
                            <strong>Hari Operasional</strong>
                            <small>Kalender dan validasi absensi</small>
                        </span>
                    </a>
                    @endcan
                </div>
            </nav>
        </aside>

        <div class="col-lg-9 d-grid gap-4">
            @can('pengaturan.view')
            <x-section-card
                id="identitas"
                title="Identitas Koperasi"
                subtitle="Nama, alamat, dan logo ini tampil di sidebar, halaman login, dan kop slip gaji."
                class="settings-section"
            >
                @if(auth()->user()->can('pengaturan.identitas.update') && ! auth()->user()->isSuperAdmin())
                <form method="POST" action="{{ route('pengaturan.identitas.update') }}" enctype="multipart/form-data" id="identitasForm">
                    @csrf
                    @method('PUT')

                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="mb-4">
                                <label class="form-label" for="nama">Nama Koperasi <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    class="form-control @error('nama') is-invalid @enderror"
                                    id="nama"
                                    name="nama"
                                    value="{{ old('nama', $identitasNama) }}"
                                    maxlength="255"
                                    required
                                >
                                @error('nama')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="alamat">Alamat</label>
                                <textarea
                                    class="form-control @error('alamat') is-invalid @enderror"
                                    id="alamat"
                                    name="alamat"
                                    rows="3"
                                    maxlength="500"
                                >{{ old('alamat', $identitasAlamat) }}</textarea>
                                @error('alamat')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Tampil di kop slip gaji bila diisi.</div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <span class="form-label d-block">Logo</span>
                            <x-image-preview :src="$identitasLogoUrl" alt="Logo koperasi" icon="bi-building" size="medium" class="mb-2" />
                            <x-form.file name="logo" accept="image/*" help="JPG/PNG/WEBP, maks. 2MB. Kosongkan untuk tetap pakai logo saat ini." />
                        </div>
                    </div>

                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 pt-4 border-top">
                        <a href="{{ route('dashboard') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save" aria-hidden="true"></i>
                            Simpan Identitas
                        </button>
                    </div>
                </form>
                @else
                <div class="settings-callout">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <div>
                        <strong>Akses hanya-baca</strong>
                        @if(auth()->user()->isSuperAdmin())
                            <p>Identitas koperasi khusus per koperasi dan dikelola oleh admin_primer masing-masing koperasi.</p>
                        @else
                            <p>Anda dapat melihat identitas koperasi, tetapi tidak memiliki izin untuk mengubahnya.</p>
                        @endif
                    </div>
                </div>
                <div class="row g-4 align-items-start mt-1">
                    <div class="col-md-4">
                        <x-image-preview :src="$identitasLogoUrl" alt="Logo koperasi" icon="bi-building" size="medium" />
                    </div>
                    <div class="col-md-8">
                        <x-detail-list class="detail-list--single">
                            <x-detail-item label="Nama" :value="$identitasNama" emphasis />
                            <x-detail-item label="Alamat" :value="$identitasAlamat ?: '-'" />
                        </x-detail-list>
                    </div>
                </div>
                @endif
            </x-section-card>
            @endcan

            @can('pengaturan.view')
            <x-section-card
                id="format-slip-gaji"
                title="Format Slip Gaji"
                subtitle="Atur susunan blok, tipografi, dan tampilan slip. Ukuran cetak dikunci ke F4 portrait dengan dua slip per lembar."
                class="settings-section"
            >
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div>
                        @if($slipGajiTemplateState['published_revision'])
                            <span class="badge text-bg-success mb-2">Template aktif revisi {{ $slipGajiTemplateState['published_revision'] }}</span>
                            <p class="mb-0 text-body-secondary small">
                                Terakhir diterbitkan {{ $slipGajiTemplateState['published_at']->translatedFormat('d F Y H:i') }}
                                @if($slipGajiTemplateState['publisher'])
                                    oleh {{ $slipGajiTemplateState['publisher'] }}
                                @endif
                            </p>
                        @else
                            <span class="badge text-bg-secondary mb-2">Menggunakan format bawaan</span>
                            <p class="mb-0 text-body-secondary small">Belum ada template khusus yang diterbitkan.</p>
                        @endif
                    </div>
                    <a href="{{ route('pengaturan.slip-gaji.edit') }}" class="btn btn-primary">
                        <i class="bi bi-layout-text-window-reverse" aria-hidden="true"></i>
                        Buka Editor Slip
                    </a>
                </div>
            </x-section-card>
            @endcan

            @can('pengaturan.view')
            <x-section-card
                id="penomoran-inventaris"
                title="Format Penomoran Inventaris"
                subtitle="Tentukan struktur kode yang dibuat otomatis ketika inventaris baru ditambahkan."
                class="settings-section"
            >
                @if(auth()->user()->can('pengaturan.kode-barang.update') && ! auth()->user()->isSuperAdmin())
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
                        @if(auth()->user()->isSuperAdmin())
                            <p>Penomoran inventaris khusus per koperasi dan dikelola oleh admin_primer masing-masing koperasi.</p>
                        @else
                            <p>Anda dapat melihat format, tetapi tidak memiliki izin untuk mengubahnya.</p>
                        @endif
                    </div>
                </div>
                <x-detail-list class="detail-list--single mt-3">
                    <x-detail-item label="Template" :value="$formatKodeBarang" emphasis />
                    <x-detail-item label="Jumlah digit urut" :value="$digitNomorUrut.' digit'" />
                    <x-detail-item label="Contoh kode" :value="$contohKodeBarang" emphasis />
                </x-detail-list>
                @endif
            </x-section-card>
            @endcan

            @can('pengaturan.view')
            <x-section-card
                id="hari-operasional"
                title="Hari Operasional"
                subtitle="Tentukan hari kerja untuk kalender dan validasi absensi. Uang makan dihitung langsung dari absensi berstatus Hadir pada periode gaji."
                class="settings-section"
            >
                @if(auth()->user()->can('pengaturan.hari-operasional.update') && ! auth()->user()->isSuperAdmin())
                <form method="POST" action="{{ route('pengaturan.hari-operasional.update') }}" id="hariOperasionalForm">
                    @csrf
                    @method('PUT')

                    <div class="settings-callout mb-4">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <div>
                            <strong>Hari yang tidak dicentang dianggap libur</strong>
                            <p>Status Hadir hanya dapat dicatat pada hari operasional. Komponen per hari kemudian menghitung catatan Hadir yang benar-benar tersimpan.</p>
                        </div>
                    </div>

                    <fieldset class="settings-fieldset">
                        <legend>Hari kerja</legend>
                        @error('hari_operasional')
                            <div class="invalid-feedback d-block mb-2">{{ $message }}</div>
                        @enderror
                        <div class="d-flex flex-wrap gap-3">
                            @foreach(\App\Services\HariOperasionalService::NAMA_HARI as $nomorHari => $namaHari)
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="hari_operasional[]"
                                    value="{{ $nomorHari }}"
                                    id="hari_operasional_{{ $nomorHari }}"
                                    @checked(in_array($nomorHari, old('hari_operasional', $hariOperasional ?? [])))
                                >
                                <label class="form-check-label" for="hari_operasional_{{ $nomorHari }}">{{ $namaHari }}</label>
                            </div>
                            @endforeach
                        </div>
                        <div class="form-text mt-2">
                            Ini cuma pola hari dalam seminggu. Tanggal libur nasional (Idul Fitri, HUT RI, dll) diatur
                            terpisah di menu
                            @can('hari-libur.view')
                                <a href="{{ route('hari-libur.index') }}">Hari Libur</a>.
                            @else
                                Hari Libur.
                            @endcan
                        </div>
                    </fieldset>

                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 pt-4 border-top">
                        <a href="{{ route('dashboard') }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save" aria-hidden="true"></i>
                            Simpan Hari Operasional
                        </button>
                    </div>
                </form>
                @else
                <div class="settings-callout">
                    <i class="bi bi-lock" aria-hidden="true"></i>
                    <div>
                        <strong>Akses hanya-baca</strong>
                        @if(auth()->user()->isSuperAdmin())
                            <p>Hari operasional khusus per koperasi dan dikelola oleh admin_primer masing-masing koperasi.</p>
                        @else
                            <p>Anda dapat melihat hari operasional, tetapi tidak memiliki izin untuk mengubahnya.</p>
                        @endif
                    </div>
                </div>
                <x-detail-list class="detail-list--single mt-3">
                    <x-detail-item
                        label="Hari operasional"
                        :value="collect($hariOperasional)->map(fn ($hari) => \App\Services\HariOperasionalService::NAMA_HARI[$hari] ?? $hari)->implode(', ')"
                        emphasis
                    />
                </x-detail-list>
                @endif
            </x-section-card>
            @endcan
        </div>
    </div>
</x-app-page>
@endsection
