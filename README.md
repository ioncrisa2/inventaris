<p align="center"><strong>PUSKOSIPA PHS</strong></p>
<p align="center">Sistem Inventaris &amp; Kepegawaian &mdash; aplikasi internal untuk mengelola unit kerja, karyawan, absensi, inventaris barang, dan penggajian dalam satu tempat.</p>

## Tentang Aplikasi

PUSKOSIPA PHS adalah aplikasi web berbasis Laravel untuk mendukung operasional kepegawaian dan inventaris sebuah instansi: data unit kerja & karyawan, rekap absensi bulanan, inventaris barang per unit kerja, penggajian, hingga laporan siap cetak/export. Antarmuka sepenuhnya berbahasa Indonesia dan hak akses diatur per role (Admin/Staff) memakai [spatie/laravel-permission](https://spatie.be/docs/laravel-permission).

### Fitur Utama

- **Kepegawaian** — Unit Kerja, data Karyawan (dengan dokumen & foto pendukung), dan Absensi harian lewat kalender bulanan.
- **Inventaris** — Pendataan barang per unit kerja, riwayat kondisi, foto/dokumen pendukung, serta cetak barcode & QR code.
- **Penggajian** — Komponen gaji (tunjangan/potongan) dan transaksi gaji bulanan per karyawan, dihitung otomatis dari gaji pokok & kehadiran.
- **Laporan** — Laporan Inventaris, Absensi, Kepegawaian, dan Penggajian; masing-masing bisa dilihat di layar, dicetak, atau diexport ke Excel.
- **Administrasi** — Manajemen Pengguna, Role & Hak Akses, dan Pengaturan Aplikasi (format kode barang otomatis, dsb).
- Tabel data konsisten di seluruh halaman: pencarian & filter, bulk action, dan paginasi dengan jumlah baris per halaman yang bisa diatur (10/25/50/100).

## Teknologi

- [Laravel 13](https://laravel.com) (PHP 8.4) dengan pola **Controller → Service → Repository** dan Form Request untuk validasi.
- Blade + komponen Blade reusable, [Bootstrap 5](https://getbootstrap.com), [Vite](https://vite.dev).
- SQLite secara default untuk pengembangan lokal (bisa diganti ke MySQL/MariaDB lewat `.env`).
- [Pest](https://pestphp.com) untuk automated testing (Feature test per modul).
- [maatwebsite/excel](https://docs.laravel-excel.com), [endroid/qr-code](https://github.com/endroid/qr-code), dan [picqer/php-barcode-generator](https://github.com/picqer/php-barcode-generator) untuk export laporan serta cetak barcode/QR barang.

## Instalasi

Prasyarat: PHP 8.4+, Composer, Node.js 18+, dan npm.

```bash
git clone https://github.com/ioncrisa2/inventaris.git
cd inventaris
composer setup
```

`composer setup` akan menyalin `.env.example` ke `.env`, generate `APP_KEY`, menjalankan migrasi, lalu install & build asset frontend. Untuk mengisi data contoh (unit kerja, karyawan, absensi, barang, penggajian, dan akun demo), jalankan:

```bash
php artisan db:seed
```

### Menjalankan Aplikasi

```bash
composer dev
```

Perintah di atas menjalankan `php artisan serve`, worker queue, dan Vite dev server sekaligus. Alternatif manual:

```bash
php artisan serve
npm run dev
```

### Testing

```bash
composer test
# atau
php artisan test
```

## Akun Demo

Setelah `php artisan db:seed`, gunakan salah satu akun berikut (password default: `password`, diatur lewat `DEMO_USER_PASSWORD` di `.env`):

| Email                    | Role  | Unit Kerja |
| ------------------------ | ----- | ---------- |
| admin@example.com        | Admin | IT         |
| it@example.com           | Staff | IT         |
| staff@example.com        | Staff | Keuangan   |
| sdm@example.com          | Staff | SDM        |
| operasional@example.com  | Staff | Operasional|
| umum@example.com         | Staff | Bag. Umum  |
| logistik@example.com     | Staff | Logistik   |

## Lisensi

Proyek ini dibangun di atas framework [Laravel](https://laravel.com), yang open-source di bawah [lisensi MIT](https://opensource.org/licenses/MIT).
