@extends('layouts.app')

@section('title', 'Panduan Singkat Operasional Sistem - Puskopdit PHS Sumsel')

@section('content')
<x-app-page class="quick-guide-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-2 border-bottom">
        <div>
            <h1 class="h4 fw-bold text-navy mb-1" style="color: #1e3a8a;">Panduan Singkat Operasional Sistem</h1>
            <p class="text-body-secondary small mb-0">Standar Operasional Prosedur (SOP) pengelolaan sistem multi-koperasi Puskopdit PHS Sumsel.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('panduan-singkat.cetak') }}" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-earmark-pdf me-1" aria-hidden="true"></i>
                Cetak / Unduh PDF Panduan
            </a>
            <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm border">
                <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
                Kembali ke Dashboard
            </a>
        </div>
    </div>

    {{-- Dokumen SOP Institusional --}}
    <div class="card border shadow-sm mb-4">
        <div class="card-body p-4 p-md-5">
            {{-- Kop Dokumen --}}
            <div class="text-center pb-3 mb-4 border-bottom" style="border-color: #1e3a8a !important; border-bottom-width: 2px !important;">
                <div class="fw-bold tracking-wide text-uppercase" style="color: #1e3a8a; font-size: 1.1rem; letter-spacing: 0.5px;">
                    Pusat Koperasi Kredit (Puskopdit) Harapan Sejahtera (PHS) Sumatera Selatan
                </div>
                <div class="fw-semibold text-secondary" style="font-size: 0.95rem;">
                    Sistem Informasi Manajemen Inventaris & Kepegawaian (Multi-Koperasi)
                </div>
                <div class="text-muted small mt-1">
                    Dokumen Standar Operasional Prosedur (SOP) & Petunjuk Teknis Pengguna
                </div>
            </div>

            {{-- Metadata Box --}}
            <div class="bg-body-tertiary border rounded p-3 mb-4 small">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div><span class="text-muted">Nomor Dokumen:</span> <strong>SOP/PHS-INV/2026-01</strong></div>
                        <div><span class="text-muted">Klasifikasi:</span> <strong>Petunjuk Operasional Resmi</strong></div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div><span class="text-muted">Sasaran:</span> <strong>Admin Pusat PHS & Admin Koperasi Primer</strong></div>
                        <div><span class="text-muted">Status Sistem:</span> <strong>Produksi (phssumsel.online)</strong></div>
                    </div>
                </div>
            </div>

            <p class="text-body leading-relaxed mb-4">
                Dokumen ini merupakan panduan operasional resmi untuk seluruh pengelola sistem di lingkungan Puskopdit Harapan Sejahtera (PHS) Sumatera Selatan beserta seluruh koperasi primer anggota/mitra. Sistem beroperasi di bawah model *multi-tenancy*, di mana data tiap koperasi terisolasi penuh pada level basis data.
            </p>

            {{-- BAB 1: MATRIKS PERAN & WEWENANG --}}
            <div class="mb-5">
                <h2 class="h6 fw-bold text-uppercase pb-2 mb-3 border-bottom" style="color: #1e3a8a; letter-spacing: 0.5px;">
                    1. Matriks Peran & Wewenang Pengguna (RBAC)
                </h2>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle small mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 22%;" class="fw-bold">Peran Pengguna</th>
                                <th style="width: 25%;" class="fw-bold">Ruang Lingkup Data</th>
                                <th style="width: 53%;" class="fw-bold">Wewenang & Batas Tanggung Jawab</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-semibold">
                                    Super Admin<br>
                                    <span class="text-muted fw-normal" style="font-size: 0.8rem;">(Admin Pusat PHS Sumsel)</span>
                                </td>
                                <td>Global (Seluruh Koperasi)</td>
                                <td>
                                    Mendaftarkan koperasi primer baru, mengontrol masa aktif langganan, membuat *role custom* untuk koperasi anggota, melakukan sinkronisasi hari libur nasional, dan memantau audit laporan konsolidasian. Bersifat *read-only* terhadap data operasional tenant.
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">
                                    Admin Primer<br>
                                    <span class="text-muted fw-normal" style="font-size: 0.8rem;">(Pengurus Koperasi Anggota)</span>
                                </td>
                                <td>Terikat 1 Koperasi</td>
                                <td>
                                    Mengatur konfigurasi identitas koperasi, struktur unit kerja, akun staf, data karyawan, inventaris barang & penyusutan aset, pencatatan absensi, hingga penerbitan slip gaji bulanan di koperasinya.
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">
                                    Staf / Kasir / Gudang<br>
                                    <span class="text-muted fw-normal" style="font-size: 0.8rem;">(Pengguna Terafiliasi)</span>
                                </td>
                                <td>Terikat 1 Koperasi / Unit Kerja</td>
                                <td>
                                    Melaksanakan tugas operasional harian sesuai izin spesifik yang diberikan oleh Super Admin / Admin Primer (misalnya pencatatan barang masuk, cetak label barcode, atau entri absensi harian).
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- BAB 2: PANDUAN ADMIN PUSAT --}}
            <div class="mb-5">
                <h2 class="h6 fw-bold text-uppercase pb-2 mb-3 border-bottom" style="color: #1e3a8a; letter-spacing: 0.5px;">
                    2. Bagian I: Panduan Operasional Admin Pusat (Super Admin PHS Sumsel)
                </h2>

                <div class="ps-3 mb-4" style="border-left: 3px solid #1e3a8a;">
                    <div class="fw-bold text-dark mb-1">Langkah 1: Akses Pusat Kontrol & Monitoring</div>
                    <p class="text-body-secondary small mb-0">
                        Masuk ke sistem melalui <code>https://phssumsel.online</code> menggunakan akun ber-role `super_admin`. Halaman dashboard utama menyajikan ringkasan statistik seluruh koperasi anggota beserta akses menu khusus <strong>Administrasi &rarr; Manajemen Koperasi</strong>.
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #1e3a8a;">
                    <div class="fw-bold text-dark mb-1">Langkah 2: Pendaftaran Koperasi Primer Baru</div>
                    <p class="text-body-secondary small mb-0">
                        Buka <strong>Administrasi &rarr; Manajemen Koperasi &rarr; Tambah Koperasi</strong>. Masukkan Nama Koperasi, batas <em>Masa Aktif Sampai</em> (kosongkan jika tanpa batas waktu), serta data Akun Admin Primer Pertama (Nama, Email, Password). Sistem secara otomatis mengeksekusi pembuatan entitas koperasi, role `admin_primer` khusus tenant tersebut, 68 hak akses operasional, serta akun pengguna pertama dalam satu transaksi database terpadu.
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #1e3a8a;">
                    <div class="fw-bold text-dark mb-1">Langkah 3: Pengelolaan Masa Berlaku & Penonaktifan Koperasi</div>
                    <p class="text-body-secondary small mb-0">
                        Buka menu <strong>Manajemen Koperasi &rarr; Edit</strong> pada baris koperasi bersangkutan. Untuk memperpanjang langganan, perbarui tanggal pada field <strong>Masa Aktif Sampai</strong>. Apabila diperlukan pemblokiran darurat (misal masa audit/tunggakan), hilangkan centang pada <strong>Koperasi aktif</strong>; tindakan ini seketika memblokir akses seluruh pengguna di koperasi tersebut.
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #1e3a8a;">
                    <div class="fw-bold text-dark mb-1">Langkah 4: Pembuatan Role Khusus per Koperasi Anggota</div>
                    <p class="text-body-secondary small mb-0">
                        Apabila koperasi anggota memerlukan pembagian wewenang khusus (misal: "Staf Kasir", "Petugas Lapangan", "Staf Gudang"), Super Admin membuatnya melalui menu <strong>Administrasi &rarr; Role & Hak Akses &rarr; Tambah Role</strong> dengan memilih target Koperasi tujuan dan menandai izin-izin yang disetujui.
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #1e3a8a;">
                    <div class="fw-bold text-dark mb-1">Langkah 5: Sinkronisasi Hari Libur Nasional</div>
                    <p class="text-body-secondary small mb-0">
                        Buka <strong>SDM & Kehadiran &rarr; Hari Libur &rarr; Sinkronisasi</strong>. Pilih tahun kerja dan jalankan sinkronisasi untuk menarik seluruh hari libur nasional resmi dari API pemerintah ke dalam kalender kerja semua koperasi.
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #1e3a8a;">
                    <div class="fw-bold text-dark mb-1">Langkah 6: Pengawasan Laporan Konsolidasi Lintas Koperasi</div>
                    <p class="text-body-secondary small mb-0">
                        Super Admin dapat meninjau seluruh laporan (Inventaris, Penyusutan Fiskal, Kepegawaian, Absensi, dan Penggajian) dengan memilih koperasi target melalui filter konteks koperasi yang tersedia pada setiap modul laporan.
                    </p>
                </div>
            </div>

            {{-- BAB 3: PANDUAN ADMIN PRIMER --}}
            <div class="mb-5">
                <h2 class="h6 fw-bold text-uppercase pb-2 mb-3 border-bottom" style="color: #1e3a8a; letter-spacing: 0.5px;">
                    3. Bagian II: Panduan Operasional Admin Primer Koperasi
                </h2>

                <div class="ps-3 mb-4" style="border-left: 3px solid #0d9488;">
                    <div class="fw-bold text-dark mb-1">Langkah 1: Konfigurasi Identitas & Pengaturan Koperasi</div>
                    <p class="text-body-secondary small mb-0">
                        Login dengan akun Admin Primer yang diberikan oleh Admin Pusat. Buka menu <strong>Administrasi &rarr; Pengaturan Aplikasi</strong> untuk menetapkan:
                        <br>&bull; <strong>Identitas Koperasi:</strong> Nama resmi, alamat kantor, nomor kontak, serta nama pimpinan penandatangan laporan.
                        <br>&bull; <strong>Penomoran Inventaris:</strong> Format pola kode barang (contoh: <code>KOP/IT/{GOL}/{TAHUN}/{URUT}</code>).
                        <br>&bull; <strong>Hari Operasional:</strong> Pengaturan hari kerja efektif mingguan sebagai dasar kalkulasi absensi.
                        <br>&bull; <strong>Format Slip Gaji:</strong> Penyusunan draf dan penerbitan format slip gaji resmi koperasi.
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #0d9488;">
                    <div class="fw-bold text-dark mb-1">Langkah 2: Penyusunan Struktur Unit Kerja & Pendaftaran Pengguna Staf</div>
                    <p class="text-body-secondary small mb-0">
                        1. Buka <strong>SDM & Kehadiran &rarr; Unit Kerja</strong> untuk menyusun departemen/divisi (misal: Unit Keuangan, Operasional, IT, Logistik).<br>
                        2. Buka <strong>Administrasi &rarr; Manajemen Pengguna</strong> untuk mendaftarkan akun staf, menautkan ke unit kerja masing-masing, dan memasangkan role yang sesuai.
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #0d9488;">
                    <div class="fw-bold text-dark mb-1">Langkah 3: Pengelolaan Data Kepegawaian & Riwayat Karyawan</div>
                    <p class="text-body-secondary small mb-0">
                        Buka <strong>SDM & Kehadiran &rarr; Karyawan &rarr; Tambah Karyawan</strong> untuk menginput profil lengkap (NIK, nama, No KTP, NPWP, jabatan, atasan langsung, status PKWTT/PKWT/Honorer, dan gaji pokok). Unggah foto serta dokumen SK Pengangkatan. Setiap mutasi, kenaikan pangkat, atau perubahan gaji dicatat melalui tab <em>Riwayat</em>.
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #0d9488;">
                    <div class="fw-bold text-dark mb-1">Langkah 4: Pendataan Inventaris Aset & Pelabelan Barcode/QR Code</div>
                    <p class="text-body-secondary small mb-0">
                        Buka <strong>Manajemen Aset &rarr; Inventaris Barang &rarr; Tambah Barang</strong>:
                        <br>&bull; Masukkan nama barang, unit kerja penanggung jawab, tanggal perolehan, harga beli, dan lokasi fisik penempatan.
                        <br>&bull; Tentukan <strong>Golongan Penyusutan Fiskal</strong> (Bukan Bangunan Kelompok 1-4 atau Bangunan).
                        <br>&bull; Unggah foto barang serta dokumen nota/garansi.
                        <br>&bull; Cetak stiker <strong>Barcode & QR Code</strong> barang (tersedia opsi cetak satuan maupun massal).
                        <br>&bull; Lakukan audit fisik secara berkala melalui tombol <strong>Catat Riwayat Kondisi</strong> (Baik, Rusak Ringan, Rusak Berat).
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #0d9488;">
                    <div class="fw-bold text-dark mb-1">Langkah 5: Pencatatan Kehadiran & Rekap Absensi</div>
                    <p class="text-body-secondary small mb-0">
                        Buka <strong>SDM & Kehadiran &rarr; Absensi</strong> untuk mencatat kehadiran harian karyawan per kalender kerja (Hadir, Izin, Sakit, Cuti, Dinas Luar Kota, atau Tanpa Keterangan). Rekapitulasi absensi bulanan akan otomatis terhubung ke kalkulator tunjangan harian pada modul penggajian.
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #0d9488;">
                    <div class="fw-bold text-dark mb-1">Langkah 6: Proses penggajian dan laporan Penerbitan Slip Gaji</div>
                    <p class="text-body-secondary small mb-0">
                        1. Atur master tunjangan dan potongan di menu <strong>Penggajian &rarr; Komponen Gaji</strong>.<br>
                        2. Buat entri bulanan di <strong>Penggajian &rarr; Transaksi Gaji &rarr; Tambah Transaksi</strong>. Sistem menghitung nominal gaji pokok, tunjangan kehadiran, dan potongan secara otomatis.<br>
                        3. Terbitkan slip gaji karyawan (tersedia cetak satuan maupun cetak massal satu periode penggajian).
                    </p>
                </div>

                <div class="ps-3 mb-4" style="border-left: 3px solid #0d9488;">
                    <div class="fw-bold text-dark mb-1">Langkah 7: Rekapitulasi Laporan & Ekspor Data</div>
                    <p class="text-body-secondary small mb-0">
                        Seluruh modul laporan (Inventaris, Penyusutan Aset, Absensi, Kepegawaian, dan Penggajian) dapat ditinjau di layar, dicetak dalam format resmi ber-kop surat, atau diekspor ke file Microsoft Excel (`.xlsx`) untuk kebutuhan pembukuan dan audit tahunan.
                    </p>
                </div>
            </div>

            {{-- BAB 4: KETENTUAN KEAMANAN & INTEGRITAS DATA --}}
            <div class="mb-4">
                <h2 class="h6 fw-bold text-uppercase pb-2 mb-3 border-bottom" style="color: #1e3a8a; letter-spacing: 0.5px;">
                    4. Standar Keamanan & Integritas Data
                </h2>
                <div class="small text-body-secondary leading-relaxed">
                    <p class="mb-2">&bull; <strong>Kerahasiaan Kredensial:</strong> Pengguna wajib menjaga kerahasiaan kata sandi akun masing-masing dan segera melakukan perubahan sandi secara berkala via menu <strong>Profil Saya</strong>.</p>
                    <p class="mb-2">&bull; <strong>Integritas Dokumen:</strong> Dokumen kepegawaian dan nota pembelian inventaris yang diunggah tersimpan secara terenkripsi/privat di server dan hanya dapat diunduh oleh pengguna berwenang.</p>
                    <p class="mb-0">&bull; <strong>Pencegahan Kebocoran Antar-Tenant:</strong> Pembatasan data level database (*fail-closed query scope*) menjamin tidak ada data koperasi yang dapat diakses atau diubah oleh koperasi lain.</p>
                </div>
            </div>

            {{-- Lembar Pengesahan Footer --}}
            <div class="pt-4 mt-5 border-top small text-muted d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>Ditetapkan di Palembang &middot; Pusat Koperasi Kredit Harapan Sejahtera (PHS) Sumsel</div>
                <div class="text-md-end">Created By : Yohanes Dwiki Septian</div>
            </div>
        </div>
    </div>
</x-app-page>
@endsection
