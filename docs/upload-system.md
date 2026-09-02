# Sistem Upload Terpadu

Dokumen ini menjelaskan rollout dan operasi upload file pada VPS. Semua file baru dicatat di `stored_files` dan `stored_file_variants`; kolom path lama tetap dipertahankan sebagai fallback selama masa transisi.

## Policy file

Sumber tunggal policy berada di `config/uploads.php`.

| Policy | Format | Batas |
| --- | --- | --- |
| `employee_photo` | JPG, JPEG, PNG, WebP | 1 file, 15 MB, crop 1:1, kamera |
| `asset_photo` | JPG, JPEG, PNG, WebP | 1 file, 15 MB, kamera |
| `asset_gallery` | JPG, JPEG, PNG, WebP | 5 file, 15 MB/file, total 50 MB |
| `logo` | JPG, JPEG, PNG, WebP | 1 file, 15 MB |
| `business_documents` | PDF, JPG, JPEG, PNG | 5 file, 20 MB/file, total 50 MB |
| `product_attachments` | PDF, JPG, JPEG, PNG, WebP, TXT | 3 file/kirim, 10 MB/file, total 20 MB |
| `calendar_import` | XLSX, XLS, CSV | 1 file, 5 MB |

SVG, HEIC, GIF, executable, arsip, dan format lain di luar tabel ditolak. Validasi menggunakan inspeksi MIME, kecocokan ekstensi, ukuran, jumlah, total ukuran, batas 8.000 piksel per sisi, dan 40 megapiksel.

## Alur penyimpanan

- Gambar operasional dinormalisasi menjadi WebP tanpa metadata/EXIF/GPS. Foto karyawan menghasilkan master 640×640 dan thumbnail 160×160; foto barang memakai sisi panjang maksimal 2.048 piksel dan thumbnail 320 piksel; logo maksimal 1.024 piksel.
- Dokumen tidak diubah. PDF diverifikasi dengan Poppler dan mendapat thumbnail halaman pertama di storage privat. PDF rusak atau password-protected ditolak.
- File asinkron masuk ke `storage/app/private/staging`, dipindai ClamAV, baru kemudian diproses ke `tenants/{koperasi_id}/{policy}/{Y}/{m}`.
- Preview dan download privat hanya diberikan ketika status `ready` dan scan `clean`/`not_required`. Nginx melayani file privat melalui lokasi `internal` setelah otorisasi Laravel.
- Multipart native tetap tersedia bila JavaScript gagal. Saat scan diwajibkan, fallback ini memindai file mentah secara sinkron sebelum menyimpan apa pun.

## Deploy VPS

1. Isi environment produksi. Gunakan password Restic yang kuat dan jangan commit nilainya.

   ```dotenv
   QUEUE_CONNECTION=database
   CLAMAV_HOST=clamav
   CLAMAV_PORT=3310
   UPLOAD_ASYNC_ENABLED=false
   UPLOAD_SCAN_REQUIRED=false
   UPLOAD_X_ACCEL_ENABLED=false
   LOCAL_BACKUP_ENABLED=true
   RESTIC_REPOSITORY=/backups/restic
   RESTIC_PASSWORD=isi-dari-secret-manager
   OWNER_BACKUP_METADATA_PATH=/var/www/html/storage/app/health/backup.json
   ```

2. Sebelum pertama kali memasang volume `app_storage`, salin isi `storage/app` lama ke volume tersebut. Jangan menghapus direktori sumber sampai jumlah dan checksum file telah diverifikasi. Volume `backup_repository` terpisah dari `app_storage`, tetapi keduanya masih berada pada VPS yang sama.
3. Bangun dan nyalakan dependency terlebih dahulu:

   ```bash
   docker compose build app
   docker compose up -d db clamav app web worker scheduler
   docker compose exec app php artisan migrate --force
   docker compose exec app php artisan optimize
   ```

4. Verifikasi dependency dan pipeline:

   ```bash
   docker compose exec clamav clamdscan --ping 30
   docker compose exec app php artisan media:backfill --dry-run
   docker compose exec app php artisan media:cleanup --dry-run
   docker compose exec app php artisan system:backup
   docker compose exec app php artisan system:backup-check
   docker compose exec app php artisan system:backup-restore-test
   ```

5. Jalankan backfill tanpa `--dry-run`, periksa halaman Kesehatan Sistem dan Storage, lalu aktifkan flag satu per satu di staging: `UPLOAD_ASYNC_ENABLED=true`, `UPLOAD_SCAN_REQUIRED=true`, kemudian `UPLOAD_X_ACCEL_ENABLED=true`. Setelah setiap perubahan, bersihkan cache konfigurasi dan lakukan smoke test upload/scan/preview/download.
6. Aktifkan flag yang sama di produksi setelah staging lulus. Kamera browser memerlukan HTTPS atau localhost.

## Operasi rutin

- Maksimal tiga upload browser berjalan paralel. Token staging kedaluwarsa setelah 24 jam.
- Scheduler menjalankan requeue scan macet setiap 10 menit dan cleanup penuh pukul 01:30 WIB.
- Backup database dan `storage/app` berjalan pukul 02:30 WIB dengan retensi 7 harian dan 4 mingguan. `restic check` dan uji restore berjalan mingguan.
- Health check memantau ClamAV, pending scan tertua, failed media job, staging backlog, orphan/missing file, kapasitas disk, usia backup, integritas repository, dan uji restore.
- Upload ditolak dengan HTTP 507 bila ruang bebas kurang dari 5% atau 2 GB, mana yang lebih ketat. Warning kapasitas menggunakan ambang pemakaian 75%, 85%, dan 95%.
- Audit privat mencatat upload, claim, penolakan scan, preview, download, replace, delete, dan cancel tanpa menyimpan isi atau nama sensitif lengkap.

## Verifikasi pengembangan

```bash
docker compose exec app php artisan test
docker compose run --rm node sh -lc 'npm run test:js && npm run build'
docker compose run --rm browser-tests
docker compose run --rm --no-deps web nginx -t
```

Browser test memakai Chromium dan mock kamera otomatis untuk memeriksa modal preview/crop, keyboard dan focus trap, progres, retry, cancel, serta layout ponsel. Sebelum rollout production, lakukan satu smoke test manual melalui HTTPS menggunakan webcam internal dan satu kamera USB: pilih perangkat, ambil foto, tutup modal, lalu pastikan indikator kamera sistem padam.

Integration test nyata bersifat opt-in agar suite harian tidak membutuhkan daemon dan repository backup:

```bash
docker compose exec -e CLAMAV_INTEGRATION=true app php artisan test --filter=ClamAvIntegrationTest
docker compose exec -e BACKUP_INTEGRATION=true app php artisan test --filter=BackupIntegrationTest
```

## Rollback

Matikan `UPLOAD_ASYNC_ENABLED`, `UPLOAD_SCAN_REQUIRED`, dan `UPLOAD_X_ACCEL_ENABLED`, lalu jalankan `php artisan optimize`. Form kembali memakai multipart dan pembacaan tetap fallback ke path lama. Migration tidak menghapus kolom atau file legacy.

Backup Restic lokal belum melindungi dari kehilangan seluruh VPS. Repository harus direplikasi ke lokasi offsite pada fase berikutnya.
