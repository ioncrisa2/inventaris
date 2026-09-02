@extends('layouts.app')

@section('title', 'Edit Data Karyawan')

@php
    $jenisTerpilih = old('jenis_perubahan', array_key_first($jenisPerubahanTersedia));
    $konfigurasiJenis = collect($jenisPerubahanTersedia)
        ->map(fn (array $jenis) => ['dokumen_wajib' => (bool) $jenis['dokumen_wajib']]);
@endphp

@section('content')
<x-form-page
    title="Edit Data Karyawan"
    subtitle="{{ $karyawan->nama_lengkap }} — {{ $karyawan->nik }}"
    :action="route('karyawan.riwayat.store', $karyawan)"
    :cancel-route="route('karyawan.show', $karyawan)"
    submit-label="Simpan Perubahan"
    class="is-wide"
>
    <div data-karyawan-change-form>
        <script type="application/json" data-karyawan-change-config>@json($konfigurasiJenis)</script>

        <div class="alert alert-secondary small">
            Nilai master karyawan akan diperbarui dan jejak perubahan disimpan permanen.
            Pastikan tanggal berlaku dan dokumen dasar keputusan sudah benar.
        </div>

        <div class="row g-3">
            <div class="col-12">
                <x-form.select
                    name="jenis_perubahan"
                    label="Jenis Perubahan"
                    :options="collect($jenisPerubahanTersedia)->mapWithKeys(fn (array $jenis, string $key) => [$key => $jenis['label']])"
                    :value="$jenisTerpilih"
                    required
                    data-karyawan-change-type
                />
                @foreach($jenisPerubahanTersedia as $key => $jenis)
                    <div class="form-text" data-karyawan-change-description="{{ $key }}" @if($key !== $jenisTerpilih) hidden @endif>
                        {{ $jenis['description'] }}
                    </div>
                @endforeach
            </div>

            <div class="col-md-6">
                <x-form.input
                    name="tanggal_berlaku"
                    label="Tanggal Berlaku"
                    type="date"
                    :value="old('tanggal_berlaku', now()->toDateString())"
                    :max="now()->toDateString()"
                    required
                />
            </div>

            <div class="col-12">
                <label class="form-label" for="alasan">
                    Alasan atau Dasar Perubahan <span class="text-danger">*</span>
                </label>
                <textarea
                    class="form-control @error('alasan') is-invalid @enderror"
                    id="alasan"
                    name="alasan"
                    rows="3"
                    maxlength="1000"
                    required
                    placeholder="Jelaskan alasan, nomor keputusan, atau konteks perubahan."
                >{{ old('alasan') }}</textarea>
                @error('alasan')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>

        <hr class="my-4">

        @if(isset($jenisPerubahanTersedia['data_pribadi']))
            <section
                class="row g-3"
                data-karyawan-change-panel="data_pribadi"
                @if($jenisTerpilih !== 'data_pribadi') hidden @endif
            >
                <div class="col-md-6">
                    <x-form.input name="nik" label="NIK Internal" :value="$karyawan->nik" maxlength="20" required />
                </div>
                <div class="col-md-6">
                    <x-form.input name="nama_lengkap" label="Nama Lengkap" :value="$karyawan->nama_lengkap" maxlength="255" required />
                </div>
                <div class="col-md-6">
                    <x-form.input name="tempat_lahir" label="Tempat Lahir" :value="$karyawan->tempat_lahir" maxlength="255" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="perubahan_tanggal_lahir">Tanggal Lahir <span class="text-danger">*</span></label>
                    <input
                        class="form-control @error('tanggal_lahir') is-invalid @enderror"
                        id="perubahan_tanggal_lahir"
                        name="tanggal_lahir"
                        type="date"
                        value="{{ old('tanggal_lahir', $karyawan->tanggal_lahir?->toDateString()) }}"
                        max="{{ now()->toDateString() }}"
                        required
                    >
                    @error('tanggal_lahir')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <x-form.select
                        name="jenis_kelamin"
                        label="Jenis Kelamin"
                        :options="collect(config('kepegawaian.jenis_kelamin'))->mapWithKeys(fn ($opsi) => [$opsi => $opsi])"
                        :value="$karyawan->jenis_kelamin"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <x-form.select
                        name="agama"
                        label="Agama"
                        :options="collect(config('kepegawaian.agama'))->mapWithKeys(fn ($opsi) => [$opsi => $opsi])"
                        :value="$karyawan->agama"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <x-form.input name="nomor_ktp" label="Nomor KTP/NIK" :value="$karyawan->nomor_ktp" maxlength="16" required />
                </div>
                <div class="col-md-6">
                    <x-form.input name="npwp" label="NPWP" :value="$karyawan->npwp" maxlength="30" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="perubahan_alamat_ktp">Alamat sesuai KTP <span class="text-danger">*</span></label>
                    <textarea
                        class="form-control @error('alamat_ktp') is-invalid @enderror"
                        id="perubahan_alamat_ktp"
                        name="alamat_ktp"
                        rows="3"
                        maxlength="2000"
                        required
                    >{{ old('alamat_ktp', $karyawan->alamat_ktp) }}</textarea>
                    @error('alamat_ktp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="perubahan_alamat_domisili">Alamat Domisili <span class="text-danger">*</span></label>
                    <textarea
                        class="form-control @error('alamat_domisili') is-invalid @enderror"
                        id="perubahan_alamat_domisili"
                        name="alamat_domisili"
                        rows="3"
                        maxlength="2000"
                        required
                    >{{ old('alamat_domisili', $karyawan->alamat_domisili) }}</textarea>
                    @error('alamat_domisili')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <x-form.select
                        name="status_perkawinan"
                        label="Status Perkawinan"
                        :options="collect(config('kepegawaian.status_perkawinan'))->mapWithKeys(fn ($opsi) => [$opsi => $opsi])"
                        :value="$karyawan->status_perkawinan"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <x-form.select
                        name="pendidikan_terakhir"
                        label="Pendidikan Terakhir"
                        :options="collect(config('kepegawaian.pendidikan_terakhir'))->mapWithKeys(fn ($opsi) => [$opsi => $opsi])"
                        :value="$karyawan->pendidikan_terakhir"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <x-form.input name="jurusan" label="Jurusan" :value="$karyawan->jurusan" maxlength="255" required />
                </div>
                <div class="col-md-6">
                    <x-form.input name="nama_sekolah" label="Sekolah/Perguruan Tinggi" :value="$karyawan->nama_sekolah" maxlength="255" required />
                </div>
                <div class="col-md-4">
                    <x-form.input name="tahun_lulus" label="Tahun Lulus" type="number" :value="$karyawan->tahun_lulus" min="1950" :max="now()->year" required />
                </div>
                <div class="col-md-4">
                    <x-form.input name="nama_pasangan" label="Nama Pasangan" :value="$karyawan->nama_pasangan" maxlength="255" help="Opsional." />
                </div>
                <div class="col-md-4">
                    <x-form.input name="jumlah_anak" label="Jumlah Anak" type="number" :value="$karyawan->jumlah_anak" min="0" help="Opsional." />
                </div>
                <div class="col-12">
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <x-image-preview
                            :src="$karyawan->foto_karyawan ? \Illuminate\Support\Facades\Storage::url($karyawan->foto_karyawan) : null"
                            alt="Foto {{ $karyawan->nama_lengkap }} saat ini"
                            icon="bi-person"
                            size="avatar"
                        />
                        <div class="flex-grow-1">
                            <x-form.file
                                name="foto_karyawan"
                                label="Ganti Foto Karyawan"
                                policy="employee_photo"
                            />
                            <div class="form-text">Kosongkan jika foto tidak berubah.</div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        @if(isset($jenisPerubahanTersedia['hubungan_kerja']))
            <section
                class="row g-3"
                data-karyawan-change-panel="hubungan_kerja"
                @if($jenisTerpilih !== 'hubungan_kerja') hidden @endif
            >
                <div class="col-md-6">
                    <label class="form-label" for="hubungan_tanggal_masuk">Tanggal Masuk Kerja <span class="text-danger">*</span></label>
                    <input
                        class="form-control @error('tanggal_masuk_kerja') is-invalid @enderror"
                        id="hubungan_tanggal_masuk"
                        name="tanggal_masuk_kerja"
                        type="date"
                        value="{{ old('tanggal_masuk_kerja', $karyawan->tanggal_masuk_kerja?->toDateString()) }}"
                        max="{{ now()->toDateString() }}"
                        required
                    >
                    @error('tanggal_masuk_kerja')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <x-form.select
                        name="status_karyawan"
                        label="Hubungan Kerja Baru"
                        :options="collect(\App\Models\Karyawan::STATUSES)->mapWithKeys(fn ($status) => [$status => $status])"
                        :value="$karyawan->status_karyawan"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <x-form.input id="hubungan_nomor_sk" name="nomor_sk_pengangkatan" label="Nomor SK/Perjanjian" :value="$karyawan->nomor_sk_pengangkatan" maxlength="255" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="hubungan_tanggal_sk">Tanggal SK/Perjanjian <span class="text-danger">*</span></label>
                    <input
                        class="form-control @error('tanggal_sk_pengangkatan') is-invalid @enderror"
                        id="hubungan_tanggal_sk"
                        name="tanggal_sk_pengangkatan"
                        type="date"
                        value="{{ old('tanggal_sk_pengangkatan', $karyawan->tanggal_sk_pengangkatan?->toDateString()) }}"
                        max="{{ now()->toDateString() }}"
                        required
                    >
                    @error('tanggal_sk_pengangkatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </section>
        @endif

        @if(isset($jenisPerubahanTersedia['mutasi_promosi']))
            <section
                class="row g-3"
                data-karyawan-change-panel="mutasi_promosi"
                @if($jenisTerpilih !== 'mutasi_promosi') hidden @endif
            >
                <div class="col-md-6">
                    <x-form.select
                        name="unit_kerja_id"
                        label="Unit Kerja Baru"
                        :options="$unitKerjas->pluck('nama_unit', 'id')"
                        :value="$karyawan->unit_kerja_id"
                        required
                    />
                </div>
                <div class="col-md-6">
                    <x-form.input name="jabatan" label="Jabatan Baru" :value="$karyawan->jabatan" maxlength="255" required />
                </div>
                <div class="col-md-6">
                    <x-form.select
                        name="atasan_langsung_id"
                        label="Atasan Langsung Baru"
                        :options="$atasanOptions->pluck('nama_lengkap', 'id')"
                        :value="$karyawan->atasan_langsung_id"
                        placeholder="Tidak ada atasan langsung"
                        help="Opsional."
                    />
                </div>
                <div class="col-md-6">
                    <x-form.input id="mutasi_nomor_sk" name="nomor_sk_pengangkatan" label="Nomor SK Mutasi/Promosi" :value="$karyawan->nomor_sk_pengangkatan" maxlength="255" required />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="mutasi_tanggal_sk">Tanggal SK <span class="text-danger">*</span></label>
                    <input
                        class="form-control @error('tanggal_sk_pengangkatan') is-invalid @enderror"
                        id="mutasi_tanggal_sk"
                        name="tanggal_sk_pengangkatan"
                        type="date"
                        value="{{ old('tanggal_sk_pengangkatan', $karyawan->tanggal_sk_pengangkatan?->toDateString()) }}"
                        max="{{ now()->toDateString() }}"
                        required
                    >
                    @error('tanggal_sk_pengangkatan')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </section>
        @endif

        @if(isset($jenisPerubahanTersedia['penyesuaian_gaji']))
            <section
                class="row g-3"
                data-karyawan-change-panel="penyesuaian_gaji"
                @if($jenisTerpilih !== 'penyesuaian_gaji') hidden @endif
            >
                <div class="col-md-6">
                    <x-form.money-input name="gaji_pokok" label="Gaji Pokok Baru" :value="$karyawan->gaji_pokok" required />
                </div>
                <div class="col-12">
                    <div class="form-text">
                        Transaksi payroll baru akan mengambil gaji yang berlaku pada periode transaksi.
                        Transaksi yang sudah tersimpan tetap mempertahankan snapshot lamanya.
                    </div>
                </div>
            </section>
        @endif

        @if(isset($jenisPerubahanTersedia['keaktifan']))
            <section
                class="row g-3"
                data-karyawan-change-panel="keaktifan"
                @if($jenisTerpilih !== 'keaktifan') hidden @endif
            >
                <div class="col-md-6">
                    <x-form.select
                        name="status_keaktifan_baru"
                        label="Status Keaktifan Baru"
                        :options="['aktif' => 'Aktif', 'nonaktif' => 'Nonaktif']"
                        :value="old('status_keaktifan_baru', $karyawan->tanggal_mengundurkan_diri ? 'aktif' : 'nonaktif')"
                        required
                    />
                </div>
                <div class="col-12">
                    <div class="form-text">
                        Jika dinonaktifkan, tanggal keluar akan mengikuti Tanggal Berlaku di atas.
                    </div>
                </div>
            </section>
        @endif

        <hr class="my-4">

        <x-form.file
            name="dokumen_pendukung"
            label="Dokumen Pendukung"
            policy="business_documents"
            multiple
            data-karyawan-change-documents
        />
        <div class="form-text fw-medium" data-karyawan-change-document-requirement></div>
        @error('dokumen_pendukung')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</x-form-page>
@endsection
