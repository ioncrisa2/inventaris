@extends('layouts.app')

@section('title', $transaksiGaji->exists ? 'Edit Transaksi Gaji' : 'Buat Transaksi Gaji')

@php
    $namaBulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
@endphp

@section('content')
<x-form-page
    :title="$transaksiGaji->exists ? 'Edit Transaksi Gaji' : 'Buat Transaksi Gaji'"
    subtitle="Pilih karyawan, periode, dan komponen tunjangan/potongan yang berlaku bulan ini. Nominal akhir dihitung ulang oleh sistem saat disimpan."
    :action="$transaksiGaji->exists ? route('transaksi-gaji.update', $transaksiGaji) : route('transaksi-gaji.store')"
    :method="$transaksiGaji->exists ? 'PUT' : 'POST'"
    :cancel-route="route('transaksi-gaji.index')"
    :submit-label="$transaksiGaji->exists ? 'Simpan Perubahan' : 'Simpan Transaksi'"
    class="is-wide"
>
    <x-slot:top>
        @error('baris')
        <div class="alert alert-danger">{{ $message }}</div>
        @enderror
    </x-slot:top>

    <x-form.section title="Karyawan & Periode" />

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            @if($transaksiGaji->exists)
                <label class="form-label">Karyawan</label>
                <div class="form-control-plaintext fw-bold">
                    {{ $transaksiGaji->karyawan->nama_lengkap }} — Rp {{ number_format($transaksiGaji->karyawan->gaji_pokok, 0, ',', '.') }}
                </div>
                <div class="form-text">Karyawan tidak bisa diganti setelah transaksi dibuat. Hapus transaksi ini dan buat yang baru jika salah pilih karyawan.</div>
                <input type="hidden" name="karyawan_id" value="{{ $transaksiGaji->karyawan_id }}">
            @else
                <x-form.select
                    name="karyawan_id"
                    label="Karyawan"
                    :options="$karyawans->mapWithKeys(fn ($karyawan) => [$karyawan->id => $karyawan->nama_lengkap.' — Rp '.number_format($karyawan->gaji_pokok, 0, ',', '.')])"
                    :value="$transaksiGaji->karyawan_id"
                    required
                    placeholder="Pilih karyawan"
                />
            @endif
        </div>

        <div class="col-md-3">
            <x-form.select
                name="bulan"
                label="Bulan"
                :options="$namaBulan"
                :value="$transaksiGaji->bulan ?? now()->month"
                required
            />
        </div>

        <div class="col-md-3">
            <x-form.input
                name="tahun"
                label="Tahun"
                type="number"
                :value="$transaksiGaji->tahun ?? now()->year"
                required
                min="2000"
                max="2100"
            />
        </div>
    </div>

    <x-form.section title="Komponen Gaji" description="Centang komponen yang berlaku, lalu sesuaikan metode atau nilainya untuk transaksi ini.">
        <x-slot:actions>
            <a href="{{ route('komponen-gaji.index') }}" class="small">Ubah di Komponen Gaji</a>
        </x-slot:actions>
    </x-form.section>

    @foreach(['Tunjangan', 'Potongan'] as $jenisGrup)
    <div class="mb-4">
        <h3 class="h6 text-body-secondary text-uppercase">{{ $jenisGrup }}</h3>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th class="table-col-width-40"></th>
                        <th>Komponen</th>
                        <th class="table-col-width-220">Metode</th>
                        <th class="table-col-width-200">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    @php $adaBaris = false; @endphp
                    @foreach($barisMaster as $baris)
                        @continue($baris['jenis'] !== $jenisGrup)
                        @php $adaBaris = true; @endphp
                        @include('transaksi-gaji._baris', ['baris' => $baris, 'bisaHapusNama' => false])
                    @endforeach
                    @foreach($barisYatim as $baris)
                        @continue($baris['jenis'] !== $jenisGrup)
                        @php $adaBaris = true; @endphp
                        @include('transaksi-gaji._baris', ['baris' => $baris, 'bisaHapusNama' => true])
                    @endforeach
                    @if(!$adaBaris)
                    <tr>
                        <td colspan="4" class="text-body-secondary">Belum ada komponen jenis {{ $jenisGrup }} di master.</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
    @endforeach

    @if(count($barisYatim))
    <p class="text-body-secondary small">
        <i class="bi bi-info-circle"></i>
        Baris bertanda "(komponen sudah dihapus dari master)" tetap muncul di sini karena merupakan bagian dari riwayat transaksi ini.
    </p>
    @endif
</x-form-page>
@endsection
