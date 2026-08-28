@extends('layouts.app')

@section('title', 'Data Saya')

@section('content')
<x-app-page>
    <x-page-header title="Data Saya" subtitle="Identitas dan informasi kepegawaian yang terhubung dengan akun login Anda." />
    <x-flash-alert />

    @if(! $karyawan)
        <x-empty-state icon="bi-person-exclamation" title="Akun belum terhubung dengan data karyawan">
            Hubungi Admin Primer koperasi untuk menghubungkan akun login Anda dengan data karyawan yang tepat.
        </x-empty-state>
    @else
        @php
            $ktp = $karyawan->nomor_ktp;
            $ktpMasked = $ktp ? str_repeat('•', max(0, strlen($ktp) - 4)).substr($ktp, -4) : '-';
        @endphp
        <div class="card content-narrow">
            <div class="card-header"><span>{{ $karyawan->nama_lengkap }}</span></div>
            <div class="card-body">
                <dl class="row g-3 mb-0">
                    <dt class="col-sm-4 text-body-secondary">NIK Internal</dt><dd class="col-sm-8">{{ $karyawan->nik }}</dd>
                    <dt class="col-sm-4 text-body-secondary">Nomor KTP</dt><dd class="col-sm-8">{{ $ktpMasked }}</dd>
                    <dt class="col-sm-4 text-body-secondary">Unit Kerja</dt><dd class="col-sm-8">{{ $karyawan->unitKerja?->nama_unit ?? '-' }}</dd>
                    <dt class="col-sm-4 text-body-secondary">Jabatan</dt><dd class="col-sm-8">{{ $karyawan->jabatan }}</dd>
                    <dt class="col-sm-4 text-body-secondary">Status</dt><dd class="col-sm-8">{{ $karyawan->status_karyawan }}</dd>
                    <dt class="col-sm-4 text-body-secondary">Atasan Langsung</dt><dd class="col-sm-8">{{ $karyawan->atasanLangsung?->nama_lengkap ?? '-' }}</dd>
                    <dt class="col-sm-4 text-body-secondary">Tanggal Masuk</dt><dd class="col-sm-8">{{ $karyawan->tanggal_masuk_kerja?->translatedFormat('d F Y') ?? '-' }}</dd>
                </dl>
            </div>
        </div>
    @endif
</x-app-page>
@endsection
