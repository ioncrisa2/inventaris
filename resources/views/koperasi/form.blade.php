@extends('layouts.app')

@section('title', $koperasi->exists ? 'Edit Koperasi' : 'Tambah Koperasi')

@section('content')
<x-form-page
    :title="$koperasi->exists ? 'Edit Koperasi' : 'Tambah Koperasi'"
    :action="$koperasi->exists ? route('koperasi.update', $koperasi) : route('koperasi.store')"
    :method="$koperasi->exists ? 'PUT' : 'POST'"
    :cancel-route="route('koperasi.index')"
    :submit-label="$koperasi->exists ? 'Simpan Perubahan' : 'Simpan Koperasi'"
>
    <x-form.section title="Informasi Koperasi" />

    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <x-form.input name="nama" label="Nama Koperasi" :value="$koperasi->nama" required autofocus maxlength="255" />
        </div>
        <div class="col-md-4">
            <x-form.input
                name="expires_at"
                label="Masa Aktif Sampai"
                type="date"
                :value="$koperasi->expires_at?->format('Y-m-d')"
                help="Kosongkan untuk masa aktif tanpa batas."
            />
        </div>
    </div>

    @if($koperasi->exists)
    <div class="form-check mb-4">
        <input
            type="checkbox"
            class="form-check-input"
            name="is_active"
            id="is_active"
            value="1"
            @checked(old('is_active', $koperasi->is_active))
        >
        <label class="form-check-label" for="is_active">Koperasi aktif</label>
        <div class="form-text">Nonaktifkan untuk memblokir login seluruh pengguna koperasi ini tanpa menunggu masa aktif habis.</div>
    </div>

    <x-form.section title="Fitur Koperasi (Override)" />
    <div class="row g-3 mb-4">
        @php
            $featureService = app(\App\Services\PlatformFeatureService::class);
            $definitions = $featureService->definitions();
            $overrides = old('feature_overrides', $koperasi->feature_overrides ?? []);
        @endphp
        @foreach($definitions as $key => $definition)
            @php
                $val = array_key_exists($key, $overrides) ? ($overrides[$key] ? '1' : '0') : '';
            @endphp
            <div class="col-md-6">
                <label class="form-label" for="feature_{{ $key }}">{{ $definition['name'] }}</label>
                <select name="feature_overrides[{{ $key }}]" id="feature_{{ $key }}" class="form-select">
                    <option value="" @selected($val === '')>Gunakan Pengaturan Global</option>
                    <option value="1" @selected($val === '1')>Nyala (Override)</option>
                    <option value="0" @selected($val === '0')>Mati (Override)</option>
                </select>
            </div>
        @endforeach
        <div class="col-12"><div class="form-text">Pengaturan ini menimpa konfigurasi fitur global dari platform untuk koperasi ini.</div></div>
    </div>
    @else
        <x-form.section title="Akun Admin Primer Pertama" />

        <div class="row g-3">
            <div class="col-md-6">
                <x-form.input name="admin_nama" label="Nama Admin Primer" :value="old('admin_nama')" required maxlength="255" />
            </div>
            <div class="col-md-6">
                <x-form.input name="admin_email" label="Email Admin Primer" type="email" :value="old('admin_email')" required maxlength="255" />
            </div>
            <div class="col-md-6">
                <x-form.input name="admin_password" label="Password Admin Primer" type="password" required />
            </div>
        </div>
    @endif
</x-form-page>
@endsection
