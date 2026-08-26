<p align="center"><strong>Sistem Inventaris &amp; Kepegawaian Koperasi</strong></p>
<p align="center">Aplikasi multi-koperasi (SaaS) untuk mengelola unit kerja, karyawan, absensi, inventaris barang, dan penggajian dalam satu tempat.</p>

## Tentang Aplikasi

Aplikasi web berbasis Laravel untuk operasional kepegawaian dan inventaris koperasi: unit kerja & karyawan, rekap absensi bulanan, inventaris barang per unit kerja, penggajian, hingga laporan siap cetak/export. Satu instance melayani banyak koperasi (tenant) sekaligus — data tiap koperasi terisolasi penuh lewat kolom `koperasi_id` (lihat [docs/multi-tenant-koperasi.md](docs/multi-tenant-koperasi.md) & [docs/panduan-super-admin.md](docs/panduan-super-admin.md)). Antarmuka berbahasa Indonesia, hak akses diatur per role memakai [spatie/laravel-permission](https://spatie.be/docs/laravel-permission).

### Fitur Utama

- **Multi-Koperasi** — satu instance untuk banyak koperasi, data terisolasi penuh; `super_admin` mengelola koperasi & masa aktif langganan, `admin_primer` mengelola koperasinya sendiri.
- **Kepegawaian** — Unit Kerja, data Karyawan (dokumen & foto pendukung), Absensi harian lewat kalender bulanan, dan sinkronisasi Hari Libur nasional.
- **Inventaris** — Pendataan barang per unit kerja (termasuk lokasi penempatan), riwayat kondisi, foto/dokumen pendukung, cetak barcode & QR code, serta laporan penyusutan aset.
- **Penggajian** — Komponen gaji (tunjangan/potongan) dan transaksi gaji bulanan per karyawan, dihitung otomatis dari gaji pokok & kehadiran.
- **Laporan** — Inventaris, Absensi, Kepegawaian, dan Penggajian; bisa dilihat di layar, dicetak, atau diexport ke Excel.
- **Administrasi** — Manajemen Pengguna, Role & Hak Akses, Pengaturan Aplikasi, dan tur onboarding interaktif untuk pengguna baru.
- Tabel data konsisten di seluruh halaman: pencarian & filter, bulk action, paginasi yang bisa diatur (10/25/50/100).

## Teknologi

- [Laravel 13](https://laravel.com) (PHP 8.4) dengan pola **Controller → Service → Repository** dan Form Request untuk validasi.
- Blade + komponen Blade reusable, [Bootstrap 5](https://getbootstrap.com), [Vite](https://vite.dev), [Shepherd.js](https://shepherdjs.dev) untuk onboarding.
- SQLite untuk pengembangan lokal (bisa diganti MySQL/MariaDB lewat `.env`, dipakai di produksi/Docker).
- [Pest](https://pestphp.com) untuk automated testing (Feature test per modul).
- [maatwebsite/excel](https://docs.laravel-excel.com), [endroid/qr-code](https://github.com/endroid/qr-code), [picqer/php-barcode-generator](https://github.com/picqer/php-barcode-generator) untuk export laporan serta cetak barcode/QR barang.

## Instalasi

### Lokal (development)

Prasyarat: PHP 8.4+, Composer, Node.js 18+, npm.

```bash
git clone https://github.com/ioncrisa2/inventaris.git
cd inventaris
composer setup
```

`composer setup` menyalin `.env.example` ke `.env`, generate `APP_KEY`, menjalankan migrasi, lalu install & build asset frontend. Untuk data contoh (koperasi demo beserta akun-akunnya), jalankan:

```bash
php artisan db:seed
```

Menjalankan aplikasi (server, queue worker, dan Vite dev server sekaligus):

```bash
composer dev
```

Testing:

```bash
composer test
```

### Docker (produksi)

```bash
cp .env.example .env
# Isi APP_KEY, APP_URL, DB_PASSWORD, dan DB_ROOT_PASSWORD sebelum melanjutkan.
docker compose config --quiet
docker compose up -d --build --wait
docker compose exec app frankenphp php-cli artisan migrate --force
docker compose exec app frankenphp php-cli artisan optimize
```

Aplikasi tersedia di `http://localhost:8081` secara default. Port dapat diubah melalui `APP_PORT` di `.env`. Image produksi memakai FrankenPHP dalam classic mode, membangun aset Vite di build stage, dan menjalankan Laravel tanpa Nginx/PHP-FPM terpisah. Lihat [Dockerfile](Dockerfile), [docker-compose.yml](docker-compose.yml), dan [Caddyfile](Caddyfile). Deploy otomatis via [.github/workflows/deploy.yml](.github/workflows/deploy.yml) saat push ke `main`.

Perintah Artisan di dalam container dijalankan melalui CLI SAPI FrankenPHP:

```bash
docker compose exec app frankenphp php-cli artisan about
```

## Akun Demo

Setelah `php artisan db:seed`, gunakan salah satu akun berikut (password default: `password`, diatur lewat `DEMO_USER_PASSWORD` di `.env`):

| Email                    | Role         | Koperasi      | Unit Kerja  |
| ------------------------ | ------------ | ------------- | ----------- |
| admin@example.com        | Super Admin  | — (lintas koperasi) | —     |
| admin.primer@example.com | Admin Primer | Koperasi Demo | —           |
| it@example.com           | Staff        | Koperasi Demo | IT          |
| staff@example.com        | Staff        | Koperasi Demo | Keuangan    |
| sdm@example.com          | Staff        | Koperasi Demo | SDM         |
| operasional@example.com  | Staff        | Koperasi Demo | Operasional |
| umum@example.com         | Staff        | Koperasi Demo | Bag. Umum   |
| logistik@example.com     | Staff        | Koperasi Demo | Logistik    |

## Lisensi

Dibangun di atas [Laravel](https://laravel.com), open-source di bawah [lisensi MIT](https://opensource.org/licenses/MIT).
