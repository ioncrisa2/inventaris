@extends('layouts.app')

@section('title', 'Panduan Singkat - Sistem Inventaris & Kepegawaian PHS Sumsel')

@section('content')
<x-app-page class="quick-guide-page">
    <x-page-header
        title="Panduan Singkat Operasional Sistem"
        subtitle="Petunjuk lengkap langkah demi langkah pengelolaan sistem multi-koperasi untuk Admin Pusat PHS Sumsel dan Admin Koperasi Primer."
    >
        <x-slot:actions>
            <a href="{{ route('panduan-singkat.cetak') }}" target="_blank" class="btn btn-primary">
                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                Cetak / Unduh PDF Panduan
            </a>
            <a href="{{ route('dashboard') }}" class="btn btn-light">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                Kembali ke Dashboard
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Banner Ringkasan --}}
    <div class="card border-primary border-opacity-25 bg-primary-subtle mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-start gap-3">
                <div class="p-3 bg-primary text-white rounded-3 fs-3">
                    <i class="bi bi-book-half"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-primary-emphasis mb-1">Pusat Bantuan & Panduan Sistem Multi-Tenant PHS Sumsel</h5>
                    <p class="text-body-secondary mb-2">
                        Sistem ini menggunakan arsitektur <strong>Multi-Koperasi (SaaS)</strong> di mana <strong>Admin Pusat (Super Admin)</strong> mengelola pendaftaran koperasi dan masa aktif langganan, sedangkan <strong>Admin Primer</strong> dan staf terafiliasi mengelola operasional harian koperasinya masing-masing secara terisolasi penuh.
                    </p>
                    <div class="d-flex flex-wrap gap-2 pt-1">
                        <span class="badge bg-primary px-3 py-2"><i class="bi bi-shield-check me-1"></i> Data Terisolasi per Koperasi</span>
                        <span class="badge bg-secondary px-3 py-2"><i class="bi bi-people me-1"></i> Role Primer & Staf Terafiliasi</span>
                        <span class="badge bg-success px-3 py-2"><i class="bi bi-printer me-1"></i> Format Siap Cetak & Export Excel</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- BAGIAN I: ADMIN PUSAT (SUPER ADMIN PHS SUMSEL) --}}
    <x-section-card
        title="Bagian I: Panduan Admin Pusat (Super Admin PHS Sumsel)"
        subtitle="Wewenang khusus vendor/pengelola pusat PHS Sumsel untuk mengontrol koperasi anggota dan konfigurasi global."
        class="mb-4"
    >
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary rounded-circle p-2 fs-6">1</span>
                            <h6 class="fw-bold mb-0">Login ke Dashboard Pusat</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Masuk dengan kredensial <code>super_admin</code>. Dashboard pusat menampilkan gambaran ringkas seluruh koperasi dan akses ke modul <strong>Administrasi &rarr; Manajemen Koperasi</strong>.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary rounded-circle p-2 fs-6">2</span>
                            <h6 class="fw-bold mb-0">Menambah Koperasi Baru</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Buka <strong>Manajemen Koperasi &rarr; Tambah Koperasi</strong>. Masukkan Nama Koperasi, Masa Aktif, serta Akun Admin Primer Pertama (Nama, Email, Password). Sistem otomatis membuatkan role <code>admin_primer</code> beserta 68 izin operasional.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary rounded-circle p-2 fs-6">3</span>
                            <h6 class="fw-bold mb-0">Kelola Masa Aktif & Status</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Super admin dapat memperpanjang masa berlaku langganan melalui kolom <em>Masa Aktif Sampai</em>, atau menonaktifkan akun koperasi sementara waktu dengan menghilangkan centang <em>Koperasi aktif</em>.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary rounded-circle p-2 fs-6">4</span>
                            <h6 class="fw-bold mb-0">Buat Role Custom per Koperasi</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Jika koperasi mitra membutuhkan jabatan/peran khusus (misal: <em>Kasir</em>, <em>Staf Gudang</em>), Super Admin yang membuatkannya di menu <strong>Role & Hak Akses</strong> dengan memilih target koperasi tujuan.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary rounded-circle p-2 fs-6">5</span>
                            <h6 class="fw-bold mb-0">Sinkronisasi Hari Libur Nasional</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Buka <strong>SDM & Kehadiran &rarr; Hari Libur &rarr; Sinkronisasi</strong>. Tarik otomatis hari libur resmi nasional dari API pemerintah agar kalender kerja seluruh koperasi selalu terbarui.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-primary rounded-circle p-2 fs-6">6</span>
                            <h6 class="fw-bold mb-0">Monitoring Lintas Koperasi</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Super admin memiliki akses baca untuk melihat rekapitulasi data inventaris, absensi, kepegawaian, dan penggajian dari seluruh koperasi anggota menggunakan filter konteks koperasi.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </x-section-card>

    {{-- BAGIAN II: ADMIN PRIMER (PENGURUS KOPERASI MITRA) --}}
    <x-section-card
        title="Bagian II: Panduan Operasional Admin Primer Koperasi"
        subtitle="Alur kerja lengkap bagi Admin Primer untuk mengelola operasional kepegawaian, inventaris, dan penggajian."
        class="mb-4"
    >
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success rounded-circle p-2 fs-6">1</span>
                            <h6 class="fw-bold mb-0">Pengaturan Awal Koperasi</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Buka <strong>Administrasi &rarr; Pengaturan Aplikasi</strong>. Atur identitas resmi koperasi (alamat, kontak, kop surat), pola format penomoran inventaris (contoh: <code>KOP/IT/{GOL}/{TAHUN}/{URUT}</code>), hari operasional kerja, dan format draf slip gaji.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success rounded-circle p-2 fs-6">2</span>
                            <h6 class="fw-bold mb-0">Susun Unit Kerja & Staf</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Tambahkan divisi/unit kerja di menu <strong>Unit Kerja</strong> (misal: Keuangan, Operasional, IT). Lalu daftarkan staf pengguna di <strong>Manajemen Pengguna</strong> dan kaitkan dengan unit kerja serta role yang sesuai.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success rounded-circle p-2 fs-6">3</span>
                            <h6 class="fw-bold mb-0">Kelola Data Kepegawaian</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Daftarkan seluruh karyawan di menu <strong>Karyawan</strong> lengkap dengan NIK, status kepegawaian (PKWTT/PKWT/Honorer), gaji pokok, jabatan, atasan langsung, foto profil, dan dokumen SK pengangkatan.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success rounded-circle p-2 fs-6">4</span>
                            <h6 class="fw-bold mb-0">Lengkapi Data Inventaris & Aset</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Catat aset di menu <strong>Inventaris Barang</strong>: tentukan golongan penyusutan (Kelompok 1-4 / Bangunan), harga perolehan, tanggal beli, lokasi, foto, dokumen nota/garansi, serta cetak label <strong>Barcode & QR Code</strong> barang.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success rounded-circle p-2 fs-6">5</span>
                            <h6 class="fw-bold mb-0">Pencatatan Absensi Harian</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Catat kehadiran karyawan di menu <strong>Absensi</strong> (Hadir, Izin, Sakit, Cuti, Dinas Luar Kota, Tanpa Keterangan). Data absensi ini otomatis menjadi dasar perhitungan tunjangan atau potongan pada penggajian.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-lg-4">
                <div class="card h-100 border shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-success rounded-circle p-2 fs-6">6</span>
                            <h6 class="fw-bold mb-0">Proses penggajian dan laporan</h6>
                        </div>
                        <p class="text-muted small mb-0">
                            Atur master <strong>Komponen Gaji</strong> (tunjangan transport, makan, potongan BPJS), lalu input <strong>Transaksi Gaji</strong> bulanan. Cetak slip gaji perorangan atau massal, dan tarik laporan siap ekspor ke Excel / PDF.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="text-body-secondary small">Sistem Informasi Manajemen Inventaris & Kepegawaian Multi-Tenant PHS Sumsel</span>
                <p class="mb-0 text-body-secondary small">Created By : Yohanes Dwiki Septian</p>
            </div>
        </x-slot:footer>
    </x-section-card>
</x-app-page>
@endsection
