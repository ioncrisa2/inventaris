@extends('layouts.app')

@section('title', 'Panduan Singkat - Sistem Inventaris & Kepegawaian')

@section('content')
<x-app-page class="quick-guide-page">
    <x-page-header
        title="Panduan Singkat"
        subtitle="Alur dasar untuk mulai menggunakan sistem inventaris dan kepegawaian."
    >
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" class="btn btn-light">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Kembali ke Dashboard
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-section-card title="Mulai dari sini" subtitle="Gunakan modul sesuai pekerjaan yang sedang Anda selesaikan.">
        <ol class="quick-guide-list">
            <li>
                <h3>Pantau ringkasan di Dashboard</h3>
                <p>Periksa jumlah inventaris, nilai aset, kondisi barang, kehadiran, dan data yang belum lengkap.</p>
            </li>
            <li>
                <h3>Lengkapi data inventaris</h3>
                <p>Catat barang, foto, dokumen pembelian, QR/barcode, serta riwayat pemeriksaan kondisinya.</p>
            </li>
            <li>
                <h3>Kelola data kepegawaian</h3>
                <p>Perbarui profil karyawan, unit kerja, jabatan, status, dokumen, dan catatan absensi.</p>
            </li>
            <li>
                <h3>Proses penggajian dan laporan</h3>
                <p>Atur komponen gaji, proses transaksi, lalu cetak atau ekspor laporan yang dibutuhkan.</p>
            </li>
        </ol>

        <x-slot:footer>
            <p class="mb-0 text-body-secondary small">Created By : Yohanes Dwiki Septian</p>
        </x-slot:footer>
    </x-section-card>
</x-app-page>
@endsection
