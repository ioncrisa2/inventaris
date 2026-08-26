@extends('layouts.print')

@section('title', 'Buku Panduan Operasional Sistem - PHS Sumsel')
@section('print_layout', 'a4-portrait')
@section('back_url', route('panduan-singkat'))

@section('content')
<style>
    .guide-doc {
        font-family: Arial, Helvetica, sans-serif;
        color: #1f2937;
        font-size: 11px;
        line-height: 1.5;
    }
    .guide-header {
        border-bottom: 2px solid #1e3a8a;
        padding-bottom: 12px;
        margin-bottom: 16px;
        text-align: center;
    }
    .guide-org-title {
        font-size: 15px;
        font-weight: 800;
        text-transform: uppercase;
        color: #1e3a8a;
        margin: 0 0 4px 0;
        letter-spacing: 0.5px;
    }
    .guide-system-title {
        font-size: 13px;
        font-weight: 700;
        color: #374151;
        margin: 0 0 4px 0;
    }
    .guide-doc-title {
        font-size: 11px;
        color: #6b7280;
        margin: 0;
    }
    .meta-box {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 8px 12px;
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        font-size: 10px;
    }
    .meta-box strong {
        color: #1e293b;
    }
    .guide-section-title {
        font-size: 12px;
        font-weight: 700;
        color: #1e3a8a;
        border-bottom: 1.5px solid #cbd5e1;
        padding-bottom: 4px;
        margin: 18px 0 10px 0;
        text-transform: uppercase;
    }
    .guide-step {
        margin-bottom: 12px;
        padding-left: 8px;
        border-left: 2px solid #3b82f6;
    }
    .guide-step-title {
        font-size: 11px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 2px;
    }
    .guide-step-desc {
        font-size: 10.5px;
        color: #475569;
        margin: 0;
    }
    .guide-step-desc code {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        padding: 1px 4px;
        border-radius: 3px;
        font-family: monospace;
        font-size: 9.5px;
    }
    .guide-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
        font-size: 10px;
    }
    .guide-table th, .guide-table td {
        border: 1px solid #cbd5e1;
        padding: 6px 8px;
        text-align: left;
    }
    .guide-table th {
        background: #f1f5f9;
        color: #1e293b;
        font-weight: 700;
    }
    .badge-pill {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 9px;
        font-weight: 700;
    }
    .badge-super { background: #dbeafe; color: #1e40af; }
    .badge-primer { background: #dcfce7; color: #166534; }
    .badge-staff { background: #fef9c3; color: #854d0e; }
    .page-break {
        page-break-before: always;
    }
    .signature-section {
        margin-top: 24px;
        display: flex;
        justify-content: space-between;
        font-size: 10px;
    }
    .signature-box {
        text-align: center;
        width: 200px;
    }
    .signature-line {
        margin-top: 50px;
        border-bottom: 1px solid #000;
    }
</style>

<div class="guide-doc">
    <div class="guide-header">
        <div class="guide-org-title">PUSAT KOPERASI KREDIT (PUSKOPDIT) HARAPAN SEJAHTERA (PHS) SUMATERA SELATAN</div>
        <div class="guide-system-title">SISTEM INFORMASI MANAJEMEN INVENTARIS & KEPEGAWAIAN (SAAS MULTI-KOPERASI)</div>
        <div class="guide-doc-title">BUKU PANDUAN OPERASIONAL & STANDAR PROSEDUR SISTEM (SOP)</div>
    </div>

    <div class="meta-box">
        <div><strong>Dokumen:</strong> Panduan Operasional Resmi</div>
        <div><strong>Platform:</strong> Multi-Tenant SaaS (phssumsel.online)</div>
        <div><strong>Tanggal Cetak:</strong> {{ now()->translatedFormat('d F Y') }}</div>
        <div><strong>Pengembang:</strong> Yohanes Dwiki Septian</div>
    </div>

    <h2 class="guide-section-title">1. Struktur Hierarki Peran & Wewenang (RBAC)</h2>
    <table class="guide-table">
        <thead>
            <tr>
                <th style="width: 22%;">Role Pengguna</th>
                <th style="width: 28%;">Cakupan Tenant</th>
                <th style="width: 50%;">Tanggung Jawab & Wewenang Utama</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="badge-pill badge-super">Super Admin</span><br><small>Admin Pusat PHS Sumsel</small></td>
                <td>Global (Lintas Koperasi)</td>
                <td>Pendaftaran koperasi baru, kontrol masa aktif langganan, pembuatan role custom, sinkronisasi hari libur nasional, dan audit laporan lintas koperasi.</td>
            </tr>
            <tr>
                <td><span class="badge-pill badge-primer">Admin Primer</span><br><small>Pengurus Koperasi Mitra</small></td>
                <td>Terisolasi 1 Koperasi</td>
                <td>Pengaturan identitas koperasi, struktur unit kerja, pendaftaran staf, manajemen karyawan & gaji, pencatatan inventaris barang, absensi, dan penerbitan slip gaji.</td>
            </tr>
            <tr>
                <td><span class="badge-pill badge-staff">Staff / Kasir / Gudang</span><br><small>Staf Terafiliasi</small></td>
                <td>Terisolasi 1 Koperasi / Unit</td>
                <td>Operasional harian sesuai hak akses yang diberikan oleh Super Admin / Admin Primer (misal: hanya input barang, kelola absensi, atau cetak barcode).</td>
            </tr>
        </tbody>
    </table>

    <h2 class="guide-section-title">2. Panduan Langkah Demi Langkah: Admin Pusat (Super Admin PHS Sumsel)</h2>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 1: Login ke Pusat Kontrol (Dashboard Super Admin)</div>
        <p class="guide-step-desc">Buka <code>https://phssumsel.online</code> dan login menggunakan akun <code>super_admin</code>. Dashboard menampilkan statistik menyeluruh serta menu khusus <strong>Administrasi &rarr; Manajemen Koperasi</strong>.</p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 2: Menambahkan Koperasi Primer Baru (Pendaftaran Tenant)</div>
        <p class="guide-step-desc">
            Buka menu <strong>Manajemen Koperasi &rarr; Tambah Koperasi</strong>. Isi Nama Koperasi, Masa Aktif, dan data <strong>Akun Admin Primer Pertama</strong> (Nama, Email, Password).<br>
            <em>Catatan Sistem:</em> Sistem secara otomatis membuatkan entitas Koperasi, role <code>admin_primer</code> untuk koperasi tersebut, memasang 68 hak akses operasional, dan membuatkan user admin primer dalam satu transaksi aman.
        </p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 3: Mengelola Masa Aktif & Penonaktifan Koperasi</div>
        <p class="guide-step-desc">
            Pada menu <strong>Manajemen Koperasi &rarr; Edit</strong>:
            <br>&bull; <strong>Perpanjang Langganan:</strong> Ubah tanggal pada kolom <em>Masa Aktif Sampai</em>.
            <br>&bull; <strong>Blokir Darurat:</strong> Hilangkan centang <em>Koperasi aktif</em> untuk memblokir seluruh akun di koperasi tsb seketika jika terjadi tunggakan atau evaluasi.
        </p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 4: Membuat Role Kustom Tambahan per Koperasi</div>
        <p class="guide-step-desc">
            Jika koperasi tertentu memerlukan jabatan baru (misal: "Kasir", "Staf Gudang", "Admin Cabang"), Super Admin membuatnya di menu <strong>Administrasi &rarr; Role & Hak Akses &rarr; Tambah Role</strong> dengan memilih target Koperasi tujuan dan mencentang izin yang diinginkan.
        </p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 5: Sinkronisasi Hari Libur Nasional</div>
        <p class="guide-step-desc">
            Buka <strong>SDM & Kehadiran &rarr; Hari Libur &rarr; Sinkronisasi</strong>. Pilih tahun dan klik <em>Sinkronkan</em> untuk menarik daftar tanggal merah resmi nasional dari API pemerintah bagi seluruh koperasi.
        </p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 6: Pengawasan Laporan Konsolidasi Lintas Koperasi</div>
        <p class="guide-step-desc">
            Super admin dapat membuka seluruh modul Laporan (Inventaris, Penyusutan, Absensi, Kepegawaian, Penggajian) dan memilih filter koperasi untuk melihat rekapitulasi data dari masing-masing koperasi mitra.
        </p>
    </div>

    <div class="page-break"></div>

    <h2 class="guide-section-title">3. Panduan Langkah Demi Langkah: Admin Primer (Koperasi Mitra)</h2>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 1: Login & Konfigurasi Pengaturan Awal Koperasi</div>
        <p class="guide-step-desc">
            Admin Primer login dengan kredensial yang diberikan oleh Admin Pusat PHS Sumsel. Buka <strong>Administrasi &rarr; Pengaturan Aplikasi</strong> untuk mengatur:
            <br>&bull; <strong>Identitas Koperasi:</strong> Nama resmi, alamat kantor, telepon, email, dan nama pimpinan penandatangan laporan.
            <br>&bull; <strong>Penomoran Inventaris:</strong> Pola format kode barang (misal: <code>KOP/IT/{GOL}/{TAHUN}/{URUT}</code>).
            <br>&bull; <strong>Hari Operasional:</strong> Centang hari kerja efektif (Senin s/d Jumat/Sabtu) sebagai acuan absensi & payroll.
            <br>&bull; <strong>Format Slip Gaji:</strong> Sesuaikan draf template dan terbitkan format slip gaji resmi.
        </p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 2: Menata Struktur Unit Kerja & Mendaftarkan Pengguna Staf</div>
        <p class="guide-step-desc">
            1. Buka <strong>SDM & Kehadiran &rarr; Unit Kerja</strong>: Masukkan unit/divisi kerja (contoh: IT, Keuangan, Operasional, SDM, Logistik).<br>
            2. Buka <strong>Administrasi &rarr; Manajemen Pengguna</strong>: Tambah akun staf pengguna, pilih unit kerja, dan pasangkan role (misal: Staff).
        </p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 3: Input & Manajemen Data Karyawan</div>
        <p class="guide-step-desc">
            Buka <strong>SDM & Kehadiran &rarr; Karyawan &rarr; Tambah Karyawan</strong>:
            <br>&bull; Isi identitas lengkap (NIK, Nama, No KTP, NPWP, Tempat/Tgl Lahir, Pendidikan, Keluarga).
            <br>&bull; Tentukan unit kerja, jabatan, atasan langsung, status (PKWTT/PKWT/Honorer), dan gaji pokok.
            <br>&bull; Unggah foto profil karyawan dan berkas dokumen pendukung (KTP, SK Pengangkatan, Ijazah).
            <br>&bull; Catat riwayat promosi, mutasi jabatan, atau penyesuaian gaji pada tab <em>Riwayat</em>.
        </p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 4: Pendataan Inventaris Aset & Cetak Barcode/QR Code</div>
        <p class="guide-step-desc">
            Buka <strong>Manajemen Aset &rarr; Inventaris Barang &rarr; Tambah Barang</strong>:
            <br>&bull; Isi nama barang, unit kerja penanggung jawab, lokasi penempatan (Kantor Pusat/Cabang/Gudang), dan tgl beli.
            <br>&bull; Pilih <strong>Golongan Penyusutan</strong> (Kelompok 1: 4 thn, Kel. 2: 8 thn, Kel. 3: 16 thn, Kel. 4: 20 thn, Bangunan: 20 thn).
            <br>&bull; Unggah foto barang dan dokumen pembelian/garansi.
            <br>&bull; Klik <strong>Barcode / QR Code</strong> untuk mencetak stiker label aset (bisa cetak individual atau massal).
            <br>&bull; Lakukan inspeksi fisik berkala dan catat pada tombol <strong>Catat Riwayat Kondisi</strong> (Baik, Rusak Ringan, Rusak Berat).
        </p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 5: Pencatatan Absensi Harian Karyawan</div>
        <p class="guide-step-desc">
            Buka <strong>SDM & Kehadiran &rarr; Absensi</strong>:
            <br>&bull; Pilih karyawan dan tanggal pada kalender bulanan, lalu klik status kehadiran (Hadir, Izin, Sakit, Cuti, Dinas Luar Kota, Alpha).
            <br>&bull; Sistem otomatis merekap total kehadiran efektif per periode penggajian.
        </p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 6: Penggajian Bulanan & Penerbitan Slip Gaji</div>
        <p class="guide-step-desc">
            1. Buka <strong>Penggajian &rarr; Komponen Gaji</strong>: Atur tunjangan tetap/harian (makan, transport) dan potongan (BPJS, kasbon).<br>
            2. Buka <strong>Penggajian &rarr; Transaksi Gaji &rarr; Tambah Transaksi</strong>: Pilih karyawan, bulan, dan tahun. Sistem otomatis menghitung gaji kotor, tunjangan per hari hadir, potongan, dan gaji bersih.<br>
            3. Klik <strong>Cetak Slip Gaji</strong> (tersedia cetak satuan maupun cetak massal satu periode).
        </p>
    </div>

    <div class="guide-step">
        <div class="guide-step-title">Langkah 7: Penarikan Laporan & Ekspor Excel / PDF</div>
        <p class="guide-step-desc">
            Buka menu laporan terkait di sidebar:
            <br>&bull; <strong>Laporan Inventaris & Penyusutan Aset:</strong> Menampilkan nilai perolehan, akumulasi penyusutan fiskal per tahun, dan nilai buku aset.
            <br>&bull; <strong>Laporan Kepegawaian & Absensi:</strong> Rekap profil karyawan, masa kerja, dan rekapitulasi kehadiran bulanan.
            <br>&bull; <strong>Laporan Penggajian:</strong> Rekap total pembayaran gaji dan rincian komponen gaji siap cetak atau ekspor Excel.
        </p>
    </div>

    <div class="signature-section">
        <div class="signature-box">
            <div>Disetujui Oleh,</div>
            <div><strong>Pengurus Puskopdit PHS Sumsel</strong></div>
            <div class="signature-line"></div>
            <div>Ketua / Pengurus Pusat</div>
        </div>
        <div class="signature-box">
            <div>Dibuat Oleh,</div>
            <div><strong>Tim Pengembang Sistem</strong></div>
            <div class="signature-line"></div>
            <div>Yohanes Dwiki Septian</div>
        </div>
    </div>
</div>
@endsection

