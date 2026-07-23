@extends('layouts.app')

@section('title', $karyawan->exists ? 'Edit Karyawan' : 'Tambah Karyawan')

@php
    $fieldIdentitas = ['nik', 'nama_lengkap', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'status_perkawinan', 'nomor_ktp', 'npwp', 'pendidikan_terakhir', 'jurusan', 'nama_sekolah', 'tahun_lulus', 'nama_pasangan', 'jumlah_anak', 'tanggal_mengundurkan_diri', 'foto_karyawan'];
    $fieldKepegawaian = ['unit_kerja_id', 'tanggal_masuk_kerja', 'status_karyawan', 'jabatan', 'nomor_sk_pengangkatan', 'tanggal_sk_pengangkatan', 'atasan_langsung_id', 'gaji_pokok'];
    $adaErrorDokumen = $errors->has('dokumen.*.jenis_dokumen') || $errors->has('dokumen.*.dokumen');

    // Tab yang aktif saat pertama render: tab pertama yang punya error kalau
    // validasi gagal, supaya user langsung lihat field yang bermasalah tanpa
    // perlu klik-klik cari sendiri.
    $tabAktif = match (true) {
        $errors->hasAny($fieldIdentitas) => 'identitas',
        $errors->hasAny($fieldKepegawaian) => 'kepegawaian',
        $adaErrorDokumen => 'dokumen',
        default => 'identitas',
    };
@endphp

@section('content')
<x-form-page
    :title="$karyawan->exists ? 'Edit Karyawan' : 'Tambah Karyawan'"
    :action="$karyawan->exists ? route('karyawan.update', $karyawan) : route('karyawan.store')"
    :method="$karyawan->exists ? 'PUT' : 'POST'"
    :cancel-route="route('karyawan.index')"
    :submit-label="$karyawan->exists ? 'Simpan Perubahan' : 'Simpan Karyawan'"
    class="is-wide"
>
    <ul class="nav nav-tabs mb-3" id="karyawanTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $tabAktif === 'identitas' ? 'active' : '' }}" id="tab-identitas-btn" data-bs-toggle="tab" data-bs-target="#tab-identitas" type="button" role="tab" aria-controls="tab-identitas" aria-selected="{{ $tabAktif === 'identitas' ? 'true' : 'false' }}">
                Data Identitas
                @if($errors->hasAny($fieldIdentitas))<span class="text-danger">•</span>@endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $tabAktif === 'kepegawaian' ? 'active' : '' }}" id="tab-kepegawaian-btn" data-bs-toggle="tab" data-bs-target="#tab-kepegawaian" type="button" role="tab" aria-controls="tab-kepegawaian" aria-selected="{{ $tabAktif === 'kepegawaian' ? 'true' : 'false' }}">
                Data Kepegawaian
                @if($errors->hasAny($fieldKepegawaian))<span class="text-danger">•</span>@endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $tabAktif === 'dokumen' ? 'active' : '' }}" id="tab-dokumen-btn" data-bs-toggle="tab" data-bs-target="#tab-dokumen" type="button" role="tab" aria-controls="tab-dokumen" aria-selected="{{ $tabAktif === 'dokumen' ? 'true' : 'false' }}">
                Dokumen Pendukung
                @if($adaErrorDokumen)<span class="text-danger">•</span>@endif
            </button>
        </li>
    </ul>

    <p class="text-body-secondary small mb-3">Semua field bertanda bintang (<span class="text-danger">*</span>) wajib diisi, kecuali yang ditandai opsional.</p>

    <div class="tab-content" id="karyawanTabContent">
        <div class="tab-pane fade {{ $tabAktif === 'identitas' ? 'show active' : '' }}" id="tab-identitas" role="tabpanel" aria-labelledby="tab-identitas-btn">
            <div class="row g-3">
                <div class="col-md-6">
                    <x-form.input name="nik" label="Nomor Induk Karyawan (NIK Internal)" :value="$karyawan->nik" required autofocus maxlength="20" />
                </div>

                <div class="col-md-6">
                    <x-form.input name="nama_lengkap" label="Nama Lengkap" :value="$karyawan->nama_lengkap" required maxlength="255" />
                </div>

                <div class="col-md-6">
                    <x-form.input name="tempat_lahir" label="Tempat Lahir" :value="$karyawan->tempat_lahir" required maxlength="255" />
                </div>

                <div class="col-md-6">
                    <x-form.input
                        name="tanggal_lahir"
                        label="Tanggal Lahir"
                        type="date"
                        :value="$karyawan->tanggal_lahir?->format('Y-m-d')"
                        required
                        :max="now()->toDateString()"
                    />
                </div>

                <div class="col-md-6">
                    <x-form.select
                        name="jenis_kelamin"
                        label="Jenis Kelamin"
                        :options="collect(config('kepegawaian.jenis_kelamin'))->mapWithKeys(fn ($opsi) => [$opsi => $opsi])"
                        :value="$karyawan->jenis_kelamin"
                        required
                        placeholder="Pilih jenis kelamin"
                    />
                </div>

                <div class="col-md-6">
                    <x-form.select
                        name="agama"
                        label="Agama"
                        :options="collect(config('kepegawaian.agama'))->mapWithKeys(fn ($opsi) => [$opsi => $opsi])"
                        :value="$karyawan->agama"
                        required
                        placeholder="Pilih agama"
                    />
                </div>

                <div class="col-md-6">
                    <x-form.select
                        name="status_perkawinan"
                        label="Status Perkawinan"
                        :options="collect(config('kepegawaian.status_perkawinan'))->mapWithKeys(fn ($opsi) => [$opsi => $opsi])"
                        :value="$karyawan->status_perkawinan"
                        required
                        placeholder="Pilih status perkawinan"
                    />
                </div>

                <div class="col-md-6">
                    <x-form.input name="nomor_ktp" label="Nomor KTP/NIK" :value="$karyawan->nomor_ktp" required maxlength="16" help="16 digit sesuai KTP." />
                </div>

                <div class="col-md-6">
                    <x-form.input name="npwp" label="NPWP" :value="$karyawan->npwp" required maxlength="30" />
                </div>

                <div class="col-md-6">
                    <x-form.select
                        name="pendidikan_terakhir"
                        label="Pendidikan Terakhir"
                        :options="collect(config('kepegawaian.pendidikan_terakhir'))->mapWithKeys(fn ($opsi) => [$opsi => $opsi])"
                        :value="$karyawan->pendidikan_terakhir"
                        required
                        placeholder="Pilih pendidikan terakhir"
                    />
                </div>

                <div class="col-md-6">
                    <x-form.input name="jurusan" label="Jurusan" :value="$karyawan->jurusan" required maxlength="255" />
                </div>

                <div class="col-md-6">
                    <x-form.input name="nama_sekolah" label="Nama Sekolah/Perguruan Tinggi" :value="$karyawan->nama_sekolah" required maxlength="255" />
                </div>

                <div class="col-md-6">
                    <x-form.input name="tahun_lulus" label="Tahun Lulus" type="number" :value="$karyawan->tahun_lulus" required min="1950" :max="now()->year" />
                </div>

                <div class="col-md-6">
                    <x-form.input name="nama_pasangan" label="Nama Pasangan" :value="$karyawan->nama_pasangan" maxlength="255" help="Opsional." />
                </div>

                <div class="col-md-6">
                    <x-form.input name="jumlah_anak" label="Jumlah Anak" type="number" :value="$karyawan->jumlah_anak" min="0" help="Opsional." />
                </div>

                <div class="col-md-6">
                    @if($karyawan->exists && $karyawan->foto_karyawan)
                    <div class="current-image mb-2">
                        <x-image-preview :src="\Illuminate\Support\Facades\Storage::url($karyawan->foto_karyawan)" alt="Foto karyawan saat ini" size="avatar" />
                        <div class="form-text">Foto saat ini — unggah file baru untuk mengganti.</div>
                    </div>
                    @endif
                    <x-form.file name="foto_karyawan" label="Foto Karyawan" :required="!($karyawan->exists && $karyawan->foto_karyawan)" accept="image/*" help="JPG/PNG/WEBP, maks. 2MB." />
                </div>
            </div>
        </div>

        <div class="tab-pane fade {{ $tabAktif === 'kepegawaian' ? 'show active' : '' }}" id="tab-kepegawaian" role="tabpanel" aria-labelledby="tab-kepegawaian-btn">
            <div class="row g-3">
                <div class="col-md-6">
                    <x-form.select
                        name="unit_kerja_id"
                        label="Unit Kerja/Bagian"
                        :options="$unitKerjas->pluck('nama_unit', 'id')"
                        :value="$karyawan->unit_kerja_id"
                        required
                        placeholder="Pilih unit kerja"
                    />
                </div>

                <div class="col-md-6">
                    <x-form.input
                        name="tanggal_masuk_kerja"
                        label="Tanggal Masuk Kerja"
                        type="date"
                        :value="$karyawan->tanggal_masuk_kerja?->format('Y-m-d')"
                        required
                        :max="now()->toDateString()"
                    />
                </div>

                <div class="col-md-6">
                    <x-form.select
                        name="status_karyawan"
                        label="Status Karyawan"
                        :options="collect(\App\Models\Karyawan::STATUSES)->mapWithKeys(fn ($status) => [$status => $status])"
                        :value="$karyawan->status_karyawan ?? 'Tetap'"
                        required
                    />
                </div>

                <div class="col-md-6">
                    <x-form.input name="jabatan" label="Jabatan" :value="$karyawan->jabatan" required maxlength="255" />
                </div>

                <div class="col-md-6">
                    <x-form.input name="nomor_sk_pengangkatan" label="Nomor SK Pengangkatan" :value="$karyawan->nomor_sk_pengangkatan" required maxlength="255" />
                </div>

                <div class="col-md-6">
                    <x-form.input
                        name="tanggal_sk_pengangkatan"
                        label="Tanggal SK Pengangkatan"
                        type="date"
                        :value="$karyawan->tanggal_sk_pengangkatan?->format('Y-m-d')"
                        required
                        :max="now()->toDateString()"
                    />
                </div>

                <div class="col-md-6">
                    <x-form.select
                        name="atasan_langsung_id"
                        label="Atasan Langsung"
                        :options="$atasanOptions->pluck('nama_lengkap', 'id')"
                        :value="$karyawan->atasan_langsung_id"
                        placeholder="Tidak ada atasan langsung"
                        help="Opsional — kosongkan kalau tidak ada atasan langsung."
                    />
                </div>

                <div class="col-md-6">
                    <x-form.money-input name="gaji_pokok" label="Gaji Pokok" :value="$karyawan->gaji_pokok" required />
                </div>
            </div>
        </div>

        <div class="tab-pane fade {{ $tabAktif === 'dokumen' ? 'show active' : '' }}" id="tab-dokumen" role="tabpanel" aria-labelledby="tab-dokumen-btn">
            @if($adaErrorDokumen)
            <div class="alert alert-danger">Ada masalah pada salah satu baris dokumen (jenis atau file tidak valid). Silakan periksa kembali bagian Dokumen Pendukung di bawah.</div>
            @endif

            @if($karyawan->exists && $karyawan->dokumen_count > 0)
            <p class="text-body-secondary small">
                <i class="bi bi-info-circle"></i>
                Karyawan ini sudah punya {{ $karyawan->dokumen_count }} dokumen tersimpan — lihat/kelola di <a href="{{ route('karyawan.show', $karyawan) }}">halaman detail</a>. Baris di bawah ini untuk menambah dokumen baru.
            </p>
            @endif

            <x-form.dokumen-repeater name="dokumen" :jenis-options="config('kepegawaian.jenis_dokumen')" />
        </div>
    </div>
</x-form-page>
@endsection
