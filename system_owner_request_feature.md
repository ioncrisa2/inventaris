# Rencana Implementasi System Owner dan Pusat Request Produk

## 1. Status dan strategi pengembangan

Dokumen ini menjadi sumber rencana untuk dua pekerjaan yang saling berkaitan tetapi harus dikerjakan secara terpisah.

| Pekerjaan | Target Git | Ketergantungan |
|---|---|---|
| Actor `system_owner`, dashboard analitik agregat, kesehatan sistem, dan penyimpanan | Dikerjakan serta dikomit langsung ke `main` | Tidak bergantung pada pusat request produk |
| Pusat request produk/fitur | Branch baru `feature/product-request-center` | Dibuat dari `main` setelah implementasi `system_owner` stabil |

Aturan pengerjaan:

- Jangan mencampur implementasi pusat request produk ke commit `system_owner` di `main`.
- Setiap commit ke `main` harus kecil, dapat diuji, dan tidak meninggalkan migrasi atau route setengah jadi.
- Jalankan seluruh test sebelum setiap commit ke `main`.
- Branch `feature/product-request-center` baru dibuat setelah seluruh checklist wajib `system_owner` selesai.
- Branch pusat request harus berawal dari commit `main` yang sudah memuat actor `system_owner` agar tidak menduplikasi logika identitas dan middleware.
- Dokumen ini boleh ikut berada di `main` sebagai rencana induk kedua pekerjaan.

### Status implementasi per 28 Agustus 2026

Fase `system_owner` telah selesai di `main` melalui commit berikut:

- `5a24667` — fondasi identitas, middleware, audit, proteksi akun, dan provisioning.
- `d8e579a` — analitik agregat global/per koperasi serta workspace owner.
- `3ff3388` — kesehatan sistem, scheduler heartbeat, dan pengukuran storage.

Quality gate terakhir: 438 test lulus dengan 2.447 assertion, build Vite lulus, route/config cache lulus, dan `git diff --check` bersih. Integrasi backup, counter error eksternal, dan telemetry mail bersifat opt-in; UI menampilkan `Tidak tersedia` sampai sumber metadata nyata dikonfigurasi. Backfill `size_bytes`, MFA/passkey, IP allowlist, dan optimasi index berbasis `EXPLAIN` tetap menjadi pekerjaan lanjutan.

## 2. Tujuan produk

### 2.1 System owner

`system_owner` adalah pemilik/pengelola produk pada tingkat platform. Posisi organisasinya berada di atas `super_admin`, tetapi hak akses aplikasinya **bukan** gabungan seluruh permission tenant.

System owner harus dapat:

- Melihat analitik agregat seluruh platform.
- Membandingkan ringkasan antar-koperasi.
- Membuka ringkasan satu koperasi dalam bentuk kartu, tabel agregat, dan grafik.
- Melihat kesehatan aplikasi, database, cache, queue, scheduler, backup, serta storage.
- Melihat versi aplikasi dan waktu deployment bila metadata tersedia.
- Mengelola dan menanggapi pusat request produk setelah fitur tersebut dibangun.

System owner tidak boleh:

- Membuka halaman detail barang, karyawan, absensi individu, transaksi gaji, slip gaji, foto, atau dokumen tenant.
- Membuat, mengubah, atau menghapus data operasional koperasi.
- Mendapat bypass permission global melalui `Gate::before()`.
- Menjadi tenant aktif atau menghasilkan record operasional dengan `koperasi_id = null`.
- Mengunduh export yang berisi record individual.
- Melihat secret, password, token, isi file log mentah, payload queue mentah, atau exception mentah yang dapat memuat data sensitif.

### 2.2 Pusat request produk

Pusat request produk menjadi saluran resmi bagi admin primer dan anggota tenant yang diberi permission untuk:

- Mengajukan permintaan fitur.
- Melaporkan bug.
- Meminta bantuan.
- Menjawab pertanyaan lanjutan dari system owner.
- Memantau status dan riwayat permintaan.

System owner bertugas melakukan triase, membalas, mengatur prioritas internal, mengubah status, menggabungkan duplikat, dan menambahkan catatan internal.

## 3. Keputusan arsitektur utama

### 3.1 Otoritas tinggi dengan akses sempit

Gunakan pemisahan berikut:

| Actor | Cakupan baca | Cakupan tulis |
|---|---|---|
| `system_owner` | Analitik agregat lintas koperasi dan observability platform | Konfigurasi owner yang aman serta pengelolaan request produk |
| `super_admin` | Control-plane dan baca lintas koperasi sesuai perilaku saat ini | Manajemen koperasi, admin primer, role, dan kewenangan yang sudah ada |
| `admin_primer` | Satu koperasi | Operasional satu koperasi sesuai permission |
| Role tenant custom | Satu koperasi | Sesuai permission yang diberikan |

`system_owner` tidak mewarisi seluruh permission `super_admin`. Route dan menu owner harus menggunakan identitas owner secara eksplisit.

### 3.2 Jangan membuka global tenant scope untuk owner

Implementasi saat ini menjadikan `isSuperAdmin()` sebagai bypass pada `KoperasiScope` dan `CurrentTenant`. Jangan langsung menggantinya dengan `isPlatformAccount()` pada seluruh tempat tersebut.

Keputusan yang harus dipertahankan:

- `KoperasiScope` tetap tidak memberi akses lintas tenant kepada `system_owner`.
- `CurrentTenant::scopeQuery()` tetap fail-closed untuk owner pada query operasional biasa.
- Akses lintas koperasi milik owner hanya melalui repository analitik khusus.
- Repository analitik boleh memakai query builder/`withoutGlobalScopes()` secara eksplisit, wajib memilih kolom agregat, dan tidak boleh mengembalikan model operasional.
- DTO/array yang dikirim ke view tidak boleh membawa primary key record operasional, nama karyawan, nomor identitas, nama barang, path file, atau data personal lain.

Dengan pembatasan ini, kesalahan controller biasa tidak otomatis membuka data detail kepada owner.

### 3.3 Istilah identitas

Tambahkan metode dengan arti yang tegas pada `App\Models\User`:

```php
isSystemOwner(): bool
isSuperAdmin(): bool
isPlatformAccount(): bool // system_owner atau super_admin
isTenantUser(): bool
```

Penggunaan:

- `isSystemOwner()` untuk dashboard owner, kesehatan sistem, storage, dan pengelolaan request produk.
- `isSuperAdmin()` tetap untuk control-plane koperasi yang sudah ada.
- `isPlatformAccount()` hanya untuk aturan yang memang berlaku bagi kedua akun global, misalnya memperbolehkan akun tanpa koperasi melewati pemeriksaan masa aktif tenant.
- Jangan memakai `isPlatformAccount()` sebagai bypass otomatis query data operasional.

## 4. Ruang lingkup implementasi system owner (`main`)

### 4.1 Role sistem dan akun owner

Perubahan yang direncanakan:

- Tambahkan `system_owner` ke `Role::SYSTEM_NAMES`.
- Tambahkan display name `System Owner`.
- Tambahkan `Role::isSystemOwnerRole()` dengan syarat nama `system_owner` dan `koperasi_id = null`.
- Tambahkan `User::isSystemOwner()` yang memverifikasi user tidak memiliki koperasi dan mempunyai role global `system_owner`.
- Tambahkan `User::isPlatformAccount()` tanpa mengubah arti `isSuperAdmin()`.
- Pastikan role `system_owner` tidak tampil sebagai role yang dapat dipilih pada form pengguna biasa.
- Pastikan super admin tidak dapat membuat, menetapkan, mengubah, atau menghapus akun owner.
- Pastikan admin primer dan role tenant tidak dapat melihat atau mengelola akun owner.
- Owner tidak perlu memperoleh seluruh isi `PermissionCatalog`; akses owner dikunci oleh middleware identitas.

Provisioning akun:

- Buat command Artisan, misalnya `system-owner:provision`.
- Email dan nama boleh diterima sebagai input command.
- Password harus diminta melalui hidden prompt; jangan menjadi argument CLI, jangan disimpan di source code, dan jangan ditulis ke log.
- Command membuat/memperbarui akun global dengan `koperasi_id = null` dan `unit_kerja_id = null`.
- Command harus memakai transaction dan memastikan hanya role `system_owner` global yang ditetapkan.
- Command harus meminta konfirmasi sebelum mengganti role/password akun yang sudah ada.
- `DatabaseSeeder` demo boleh membuat owner hanya pada environment `local/testing`, menggunakan konfigurasi demo yang aman.
- Produksi tidak boleh mengandalkan seeder demo untuk membuat owner.
- Setelah perubahan role, cache Spatie Permission wajib dibersihkan.

Catatan integritas:

- Constraint unik gabungan yang memuat `koperasi_id = null` dapat berperilaku berbeda antar-database. Selain pengecekan aplikasi, tambahkan test yang memastikan hanya satu role global `system_owner` untuk guard `web` yang digunakan.
- Boleh ada lebih dari satu akun manusia dengan role owner jika diperlukan untuk audit personal, tetapi jangan memakai akun bersama.

### 4.2 Middleware dan route

Tambahkan middleware:

```text
App\Http\Middleware\EnsureIsSystemOwner
alias: system_owner
```

Rencana route:

```text
GET /owner                         owner.dashboard
GET /owner/analytics               owner.analytics
GET /owner/analytics/koperasi/{koperasi} owner.analytics.koperasi
GET /owner/system-health           owner.system-health
GET /owner/storage                 owner.storage
```

Aturan route:

- Semua route menggunakan `auth` dan `system_owner`.
- Tambahkan throttle pada endpoint health/storage yang melakukan pemeriksaan mahal.
- Jangan letakkan route owner di balik permission tenant.
- Jangan membuat endpoint detail record operasional.
- Parameter koperasi harus divalidasi dengan `Rule::exists('koperasi', 'id')` atau route binding yang hanya dipakai sebagai filter agregasi.
- Period filter harus memakai request class dengan format tanggal/periode yang ketat dan batas rentang maksimum.
- Request tanpa periode memakai default yang terdokumentasi, misalnya 12 bulan terakhir.

Penyesuaian autentikasi/layout:

- `EnsureKoperasiActive` harus memperbolehkan `isPlatformAccount()` melewati pemeriksaan koperasi aktif, tetapi perubahan tersebut tidak boleh memengaruhi global scope data.
- Setelah login, owner diarahkan ke `owner.dashboard`, bukan dashboard operasional tenant.
- Akses profil owner harus tetap tersedia. Bila perlu, pindahkan route profil ke group `auth` atau pastikan middleware platform mengizinkannya.
- Navigasi owner hanya menampilkan Ringkasan Platform, Analitik Koperasi, Kesehatan Sistem, Penyimpanan, Profil, dan nantinya Request Produk.
- Jangan menampilkan menu CRUD operasional maupun Manajemen Koperasi kepada owner kecuali kelak ada keputusan produk terpisah.

### 4.3 Lapisan aplikasi

Tambahkan komponen terpisah dari dashboard tenant:

```text
app/Http/Controllers/SystemOwnerDashboardController.php
app/Http/Controllers/OwnerAnalyticsController.php
app/Http/Controllers/SystemHealthController.php
app/Http/Controllers/StorageUsageController.php
app/Http/Requests/Owner/OwnerAnalyticsRequest.php
app/Services/OwnerAnalyticsService.php
app/Services/SystemHealthService.php
app/Services/StorageUsageService.php
app/Repositories/OwnerAnalyticsRepository.php
app/Support/OwnerAnalyticsPrivacy.php
```

Prinsip lapisan:

- Controller hanya mengotorisasi, memvalidasi filter, memanggil service, dan memilih view.
- Repository bertanggung jawab atas query agregat lintas tenant.
- Service membentuk metrik, tren, status, format waktu, dan cache key.
- Privacy helper menerapkan minimum cohort, pembulatan, dan suppression.
- View hanya menerima hasil siap tampil; jangan mengirim builder atau model mentah.
- Gunakan `Chart.js` yang sudah menjadi dependency proyek; tidak perlu menambah library grafik baru.

### 4.4 Analitik platform global

Metrik minimum:

- Total koperasi.
- Koperasi aktif, nonaktif, kedaluwarsa, dan akan kedaluwarsa dalam 30 hari.
- Total akun tenant dan akun tenant aktif berdasarkan definisi aktivitas yang disepakati.
- Total karyawan aktif/nonaktif secara agregat.
- Total inventaris dan total harga perolehan.
- Distribusi kondisi inventaris.
- Total nilai buku dan penyusutan agregat bila perhitungannya dapat digunakan ulang secara efisien.
- Tren absensi per status dan persentase hadir per bulan/periode.
- Total penggajian per bulan tanpa rincian penerima.
- Pertumbuhan jumlah barang, pengguna, dan karyawan per bulan.
- Total pemakaian file aplikasi.
- Jumlah request produk per status setelah pusat request tersedia.

Filter minimum:

- Rentang waktu.
- Koperasi (semua atau satu koperasi).
- Modul/metrik.

Definisi harus eksplisit:

- Tentukan apakah "pengguna aktif" berarti akun yang tidak dinonaktifkan atau user yang login dalam periode tertentu. Saat ini belum terlihat atribut `last_login_at`; jika analitik aktivitas login diperlukan, tambahkan kolom/event yang sesuai dan jelaskan masa retensinya.
- Nilai uang dihitung dengan decimal/BCMath, bukan float.
- Zona waktu laporan menggunakan `Asia/Jakarta`/timezone aplikasi.
- Grafik bulan tanpa data tetap menampilkan titik nol agar urutan waktu stabil.
- Data soft-deleted hanya dihitung jika model benar-benar mendukung soft delete dan definisi metrik menyatakannya.

### 4.5 Ringkasan per koperasi

Owner boleh memilih satu koperasi dan melihat:

#### Inventaris

- Total barang.
- Total harga perolehan.
- Nilai buku dan penyusutan total.
- Distribusi kategori, jenis, dan kondisi.
- Jumlah barang belum diperiksa, tanpa foto, atau tanpa nota.
- Tren penambahan barang per bulan.

Tidak boleh menampilkan kode barang, nama barang, lokasi detail, foto, dokumen, atau ID barang.

#### Kepegawaian

- Jumlah karyawan aktif/nonaktif.
- Distribusi unit kerja dan status kepegawaian.
- Tren masuk/keluar secara bulanan.
- Persentase kelengkapan data secara agregat.

Tidak boleh menampilkan nama, NIK/KTP, alamat, nomor telepon, dokumen, foto, atau ID karyawan.

#### Absensi

- Jumlah status kehadiran per periode.
- Persentase hadir.
- Tren bulanan.
- Perbandingan dengan periode sebelumnya.

Tidak boleh menampilkan kalender atau riwayat absensi individu.

#### Penggajian

- Total gaji bersih per bulan.
- Total tunjangan dan potongan secara agregat.
- Perubahan persentase dibanding periode sebelumnya.
- Jumlah transaksi yang masuk perhitungan.

Tidak boleh menampilkan gaji pokok/gaji bersih individu, slip, komponen milik individu, atau nama penerima.

Aturan privasi khusus:

- Terapkan minimum cohort, disarankan minimal 5 orang/transaksi untuk rata-rata atau pecahan kelompok sensitif.
- Jika cohort di bawah batas, tampilkan `Data tidak cukup untuk ditampilkan`, bukan angka presisi.
- Total organisasi dapat tetap ditampilkan jika disetujui, tetapi rata-rata kelompok kecil harus disembunyikan.
- Hindari filter kombinasi yang memungkinkan owner menyimpulkan data satu individu.
- Jangan menyediakan export detail. Jika export agregat dibuat, gunakan endpoint owner khusus dan test kolom yang diekspor.

### 4.6 Kesehatan sistem

Tampilkan status ringkas `Sehat`, `Peringatan`, `Kritis`, atau `Tidak diketahui`, waktu pemeriksaan terakhir, dan pesan yang sudah disanitasi.

Pemeriksaan minimum:

| Komponen | Metrik |
|---|---|
| Aplikasi | Versi/release, environment label, uptime bila tersedia |
| Database | Konektivitas, latency query ringan, jumlah koneksi bila didukung |
| Cache | Read/write probe dengan key sementara dan TTL singkat |
| Queue | Jumlah job tertunda, job gagal, umur job tertua |
| Scheduler | Timestamp heartbeat terakhir |
| Backup | Waktu, status, dan ukuran backup terakhir bila integrasi tersedia |
| Storage | Kapasitas, terpakai, tersedia, dan persentase |
| Mail | Status konfigurasi dan error pengiriman terakhir bila dicatat |
| Error aplikasi | Jumlah error tersanitasi per periode, bukan isi log mentah |

Ketentuan:

- Health page tidak boleh menampilkan `APP_KEY`, kredensial database, DSN, token API, full path sensitif, payload job, atau stack trace lengkap.
- Gunakan timeout pendek per check agar satu dependency tidak menahan seluruh halaman.
- Kegagalan satu check menjadi hasil terstruktur; jangan menjatuhkan seluruh dashboard.
- Cache hasil check mahal selama interval pendek, misalnya 30–60 detik.
- Tambahkan scheduler heartbeat melalui cache/database yang diperbarui oleh scheduled command setiap menit.
- Status backup harus berasal dari metadata proses backup yang nyata. Jangan menyatakan backup sehat hanya karena folder backup ada.
- Jika metrik host tidak tersedia pada shared hosting/container, tampilkan `Tidak tersedia`, bukan nilai rekaan.
- Endpoint health publik `/up` tetap minimal; detail kesehatan hanya untuk owner.

### 4.7 Penggunaan penyimpanan

Kategori minimum:

- Ukuran database.
- Foto barang.
- Dokumen barang.
- Foto karyawan.
- Dokumen karyawan dan dokumen riwayat.
- Logo/asset unggahan tenant.
- Lampiran request produk setelah fitur tersedia.
- Backup.
- Ruang storage total, terpakai, dan tersedia.

Strategi pengukuran:

- Untuk disk lokal, gunakan API filesystem/PHP pada disk yang telah dikonfigurasi, bukan path dari input pengguna.
- Untuk object storage, gunakan metadata/API provider dan beri timeout/caching.
- File yang baru diunggah sebaiknya menyimpan `size_bytes` agar ringkasan tidak selalu melakukan recursive scan.
- Untuk data lama tanpa ukuran, sediakan proses backfill terkontrol atau scan berkala melalui command/queue.
- Hitung storage per koperasi melalui relasi parent yang memiliki `koperasi_id`; jangan menebak tenant dari nama folder saja.
- Bedakan `logical application files` dengan total pemakaian volume server.
- Tampilkan waktu pengukuran terakhir dan tandai data sebagai estimasi bila berasal dari scan/cache.
- Jangan menyediakan link langsung ke file dari dashboard storage owner.

### 4.8 Cache, performa, dan snapshot

- Buat namespace cache khusus, misalnya `owner-analytics:v1:*`.
- Cache harus memuat periode, koperasi, jenis metrik, dan versi skema pada key.
- Cache global owner tidak boleh menggunakan bucket tenant dari `DashboardCache` yang sekarang.
- Invalidasi real-time tidak wajib untuk semua grafik; tetapkan freshness yang masuk akal, misalnya 5–15 menit untuk analitik dan 30–60 detik untuk health.
- Query besar wajib memiliki index pendukung pada `koperasi_id`, tanggal transaksi, tanggal absensi, dan kolom periode yang digunakan.
- Evaluasi dengan `EXPLAIN` pada database produksi atau salinan representatif sebelum menambah index.
- Jika query langsung tetap berat, tambahkan tabel snapshot agregat dan scheduled refresh pada fase optimasi, bukan langsung menggandakan seluruh data.
- Dashboard tetap harus menampilkan sebagian widget ketika satu widget gagal.

### 4.9 Audit dan keamanan

Tambahkan audit khusus akses owner, minimal mencatat:

- `actor_user_id`.
- Jenis aksi/halaman.
- Koperasi filter jika ada.
- Rentang waktu filter.
- IP dan user agent secukupnya.
- Timestamp.
- Status berhasil/gagal.

Jangan simpan hasil data agregat lengkap di audit log.

Pengamanan tambahan:

- MFA wajib untuk owner dijadikan pekerjaan keamanan tersendiri jika aplikasi belum memiliki MFA.
- Regenerasi session setelah login dan perubahan password.
- Session timeout lebih pendek untuk owner bila layak.
- Rate limit endpoint health, storage, dan export.
- Notifikasi ketika akun owner baru dibuat atau kredensialnya diubah.
- Setiap akun owner harus individual; tidak ada akun bersama.
- Pastikan owner tidak dapat dipilih melalui mass assignment atau request `role_id` biasa.

### 4.10 Tampilan system owner

Halaman minimum:

```text
resources/views/owner/dashboard.blade.php
resources/views/owner/analytics/index.blade.php
resources/views/owner/analytics/koperasi.blade.php
resources/views/owner/system-health.blade.php
resources/views/owner/storage.blade.php
resources/js/pages/owner-dashboard.js
```

Ketentuan UI:

- Gunakan pola visual, komponen Bootstrap, token CSS, dan Chart.js yang sudah ada.
- Setiap grafik memiliki judul, periode, legenda, tooltip, fallback tabel/teks, dan empty state.
- Warna bukan satu-satunya penanda kondisi sehat/peringatan/kritis.
- Nilai uang memakai format Rupiah yang konsisten.
- Grafik tidak boleh menyisipkan data personal pada HTML dataset.
- Berikan indikator `Diperbarui ...` pada data yang menggunakan cache/snapshot.
- Tampilkan banner identitas `System Owner` agar konteks akun global selalu jelas.
- Jangan menampilkan tombol menuju CRUD tenant dari kartu analitik.

## 5. Peta perubahan file system owner

File yang kemungkinan berubah:

```text
app/Models/User.php
app/Models/Role.php
app/Http/Middleware/EnsureKoperasiActive.php
app/Support/NavigationMenu.php
app/Services/UserService.php
app/Policies/UserPolicy.php
app/Http/Controllers/UserController.php
app/Http/Requests/User/StoreUserRequest.php
app/Http/Requests/User/UpdateUserRequest.php
bootstrap/app.php
routes/web.php
database/seeders/PermissionSeeder.php
database/seeders/DatabaseSeeder.php
tests/Pest.php
```

File baru yang kemungkinan diperlukan:

```text
app/Console/Commands/ProvisionSystemOwner.php
app/Http/Middleware/EnsureIsSystemOwner.php
app/Http/Controllers/SystemOwnerDashboardController.php
app/Http/Controllers/OwnerAnalyticsController.php
app/Http/Controllers/SystemHealthController.php
app/Http/Controllers/StorageUsageController.php
app/Http/Requests/Owner/OwnerAnalyticsRequest.php
app/Repositories/OwnerAnalyticsRepository.php
app/Services/OwnerAnalyticsService.php
app/Services/SystemHealthService.php
app/Services/StorageUsageService.php
app/Support/OwnerAnalyticsPrivacy.php
resources/views/owner/*
resources/js/pages/owner-dashboard.js
tests/Feature/SystemOwnerAuthorizationTest.php
tests/Feature/SystemOwnerAnalyticsTest.php
tests/Feature/SystemHealthTest.php
tests/Feature/StorageUsageTest.php
```

Nama final boleh menyesuaikan konvensi setelah implementasi dimulai, tetapi pemisahan tanggung jawabnya harus dipertahankan.

## 6. Checklist implementasi system owner (`main`)

### 6.1 Persiapan

- [x] Pastikan berada di branch `main` dan working tree bersih sebelum mulai implementasi.
- [ ] Tarik/selaraskan perubahan terbaru `main` sesuai workflow repository.
- [x] Jalankan baseline `php artisan test`.
- [x] Catat waktu test dan kegagalan yang sudah ada sebelum perubahan.
- [x] Inventarisasi seluruh pemanggilan `isSuperAdmin()` dan klasifikasikan: khusus super admin, akun platform, atau bypass tenant.
- [x] Tetapkan daftar metrik MVP dan definisi matematisnya.
- [ ] Tetapkan sumber metadata backup/deployment yang benar-benar tersedia di environment target.

### 6.2 Identitas dan otorisasi

- [x] Tambahkan role sistem `system_owner` dan display name.
- [x] Tambahkan `User::isSystemOwner()`.
- [x] Tambahkan `User::isPlatformAccount()`.
- [x] Tambahkan middleware `EnsureIsSystemOwner` dan alias `system_owner`.
- [x] Izinkan akun platform melewati pemeriksaan masa aktif tenant tanpa membuka tenant scope.
- [x] Buat redirect dashboard khusus berdasarkan actor setelah login.
- [x] Sembunyikan role owner dari semua form role/user biasa.
- [x] Blok super admin/admin primer/role tenant mengelola akun owner pada policy dan service.
- [x] Pastikan owner tidak memperoleh permission mutasi tenant.
- [x] Pastikan owner tidak memperoleh bypass melalui `Gate::before()`.
- [x] Pastikan akun owner dapat mengubah profil/password sendiri melalui jalur aman.

### 6.3 Provisioning

- [x] Buat command provisioning owner dengan password hidden prompt.
- [x] Bungkus pembuatan user dan assignment role dalam transaction.
- [x] Tambahkan validasi email unik dan akun global.
- [x] Bersihkan permission cache setelah assignment.
- [x] Tambahkan skenario demo hanya untuk local/testing jika dibutuhkan. (Diputuskan tidak membuat akun demo owner; hanya role yang di-seed.)
- [x] Tambahkan test command: membuat, memperbarui, membatalkan, dan menolak kondisi tidak aman.
- [x] Dokumentasikan prosedur provisioning produksi tanpa menaruh kredensial di repository.

### 6.4 Analitik

- [x] Buat request validator periode dan koperasi.
- [x] Buat repository agregat lintas tenant khusus owner.
- [x] Buat service analitik global.
- [x] Buat service ringkasan per koperasi.
- [x] Terapkan minimum cohort untuk data sensitif.
- [x] Pastikan DTO/array tidak membawa data individual.
- [x] Tambahkan cache namespace owner.
- [ ] Tambahkan index setelah verifikasi `EXPLAIN` bila diperlukan.
- [x] Buat dashboard global.
- [x] Buat halaman ringkasan per koperasi tanpa drill-down operasional.
- [x] Buat grafik menggunakan Chart.js yang sudah tersedia.
- [x] Tambahkan empty/error states. (Loading async tidak diperlukan karena halaman server-rendered.)
- [x] Tambahkan audit akses analitik owner.

### 6.5 Kesehatan sistem dan storage

- [x] Implementasikan database health check dengan latency.
- [x] Implementasikan cache probe yang aman.
- [x] Implementasikan statistik queue dan failed jobs yang disanitasi.
- [x] Implementasikan scheduler heartbeat.
- [x] Integrasikan metadata backup atau tampilkan `Tidak tersedia` secara jujur.
- [x] Implementasikan ringkasan error tanpa stack trace/payload mentah.
- [x] Implementasikan ukuran database sesuai driver yang dipakai.
- [x] Implementasikan kapasitas local/object storage sesuai environment.
- [x] Implementasikan kategori file aplikasi.
- [ ] Rencanakan/backfill `size_bytes` untuk file lama bila diperlukan.
- [x] Cache pemeriksaan mahal dan beri timestamp pengukuran.
- [x] Tambahkan throttle route.
- [ ] Uji kondisi dependency sehat, gagal, timeout, dan tidak tersedia.

### 6.6 Pengujian keamanan tenant

- [x] Owner dapat membuka semua route `/owner/*`.
- [x] Super admin mendapat 403 pada route owner.
- [x] Admin primer dan role tenant mendapat 403 pada route owner.
- [x] Guest diarahkan ke login.
- [ ] Owner tidak dapat membuka route detail barang.
- [ ] Owner tidak dapat membuka route detail karyawan.
- [ ] Owner tidak dapat membuka absensi individu.
- [ ] Owner tidak dapat membuka transaksi/slip gaji.
- [x] Owner tidak dapat membuat, mengubah, atau menghapus data operasional.
- [ ] Owner tidak dapat mengunduh foto/dokumen tenant.
- [x] Response analitik tidak memuat nama, email, NIK/KTP, path file, atau primary key operasional.
- [x] Filter koperasi tidak menerima ID yang tidak valid.
- [x] Query agregat tidak bocor melalui global cache antar-filter.
- [x] Akun non-owner tanpa koperasi tetap fail-closed.
- [x] Perilaku super admin yang sudah ada tidak berubah.
- [x] Perilaku tenant isolation yang sudah ada tetap lulus.

### 6.7 Quality gate dan commit `main`

- [x] Jalankan `vendor/bin/pint --test` atau formatter sesuai standar proyek.
- [x] Jalankan `npm run build`.
- [x] Jalankan test khusus system owner.
- [x] Jalankan seluruh `php artisan test`.
- [ ] Tinjau query count dan performa halaman dengan data representatif.
- [x] Tinjau output HTML/JSON untuk kebocoran data detail.
- [x] Tinjau `git diff` dan pastikan tidak ada secret atau file environment.
- [x] Commit identitas/middleware ke `main` sebagai unit yang lulus test.
- [x] Commit analitik owner ke `main` sebagai unit yang lulus test.
- [x] Commit health/storage ke `main` sebagai unit yang lulus test.
- [x] Jangan menunggu pusat request produk untuk menyelesaikan system owner.

## 7. Notes implementasi pusat request produk (branch baru)

> Pekerjaan pada bagian ini **tidak dikerjakan langsung di `main`**. Setelah system owner selesai dan stabil, buat branch `feature/product-request-center` dari `main` terbaru.

### 7.1 Catatan actor pengaju

Kode saat ini mengenal role sistem `super_admin` dan `admin_primer`; anggota tenant lain memakai role custom. Karena itu:

- `admin_primer` dapat menerima permission request produk secara default.
- "Anggota primer" tidak perlu langsung dijadikan role sistem baru hanya untuk fitur ini.
- Anggota/staff tenant diberi akses melalui permission katalog.
- Jika istilah bisnis `anggota_primer` memang membutuhkan actor sistem tersendiri, buat keputusan dan rencana terpisah agar tidak mengubah hierarki role tanpa kebutuhan.

Permission tenant yang direncanakan:

```text
product-request.view
product-request.create
product-request.reply
product-request.close
```

System owner mengelola seluruh request melalui `isSystemOwner()`, bukan permission yang dapat dicentang oleh tenant.

### 7.2 Jenis dan workflow

Jenis request:

- `feature` — permintaan fitur baru/perubahan fitur.
- `bug` — perilaku aplikasi yang tidak sesuai.
- `support` — bantuan penggunaan/konfigurasi.
- `other` — masukan lain yang masih terkait produk.

Status utama:

```text
submitted
under_review
needs_information
considered
planned
in_progress
completed
rejected
duplicate
closed
```

Transisi minimum:

```text
Diajukan -> Ditinjau -> Butuh Informasi -> Dipertimbangkan
                                      |-> Direncanakan -> Dikerjakan -> Selesai
                                      |-> Ditolak
                                      |-> Duplikat
```

Aturan:

- Pengaju dapat membuat, membaca sesuai scope, membalas, dan menutup request yang diizinkan.
- Hanya owner dapat mengatur prioritas internal, assignment, status roadmap, duplikat, dan catatan internal.
- Request `completed/rejected/duplicate/closed` tidak dapat menerima perubahan tenant selain dibuka kembali melalui alur yang ditentukan.
- Setiap transisi status dicatat sebagai history yang immutable.
- Status harus divalidasi melalui PHP backed enum atau transition service, bukan string bebas dari request.

### 7.3 Model data yang direncanakan

Gunakan nama domain `product_requests` agar tidak rancu dengan class HTTP `Request`.

#### `product_requests`

- `id`.
- `public_id` atau `ticket_number` unik, misalnya `REQ-2026-000001`.
- `koperasi_id` wajib untuk request tenant.
- `created_by` mengarah ke user pengaju.
- `assigned_to` nullable dan hanya menunjuk owner/pengelola yang sah.
- `type` berupa string tervalidasi enum.
- `module` nullable, dari daftar modul yang dikendalikan aplikasi.
- `title`.
- `description`.
- `requester_priority` sebagai persepsi pengaju.
- `internal_priority` nullable, hanya owner.
- `status`.
- `duplicate_of_id` nullable self-reference.
- `first_responded_at` nullable.
- `last_activity_at`.
- `resolved_at` nullable.
- `closed_at` nullable.
- timestamps.

Index minimum:

- `(koperasi_id, status, last_activity_at)`.
- `(created_by, last_activity_at)`.
- `(assigned_to, status)`.
- `(type, status)`.
- unique `ticket_number`.

#### `product_request_messages`

- `id`.
- `product_request_id`.
- `author_user_id`.
- `visibility`: `public` atau `internal`.
- `body`.
- timestamps.

Catatan internal hanya boleh dibuat/dibaca system owner. Tenant tidak boleh menerima jumlah, preview, atau notifikasi catatan internal.

#### `product_request_attachments`

- `id`.
- `product_request_id`.
- `message_id` nullable jika lampiran terkait balasan.
- `uploaded_by`.
- `disk`.
- `path`.
- `original_name`.
- `mime_type`.
- `size_bytes`.
- `checksum` nullable.
- timestamps.

Aturan file:

- Allowlist MIME dan extension.
- Batas ukuran per file dan total per request.
- Nama file storage diacak; jangan memakai nama asli sebagai path.
- Download hanya melalui controller terotorisasi, bukan public URL langsung.
- Pertimbangkan malware scanning jika environment mendukung.
- Penghapusan file dan row database harus memakai pola transactional file storage yang sudah ada di proyek.

#### `product_request_status_histories`

- `id`.
- `product_request_id`.
- `changed_by`.
- `from_status` nullable.
- `to_status`.
- `reason` nullable.
- `created_at`.

History tidak diedit atau dihapus dari UI.

### 7.4 Scope dan otorisasi

- Model request tenant menggunakan `koperasi_id` dan prinsip tenant scoping proyek.
- Admin primer dapat melihat seluruh request koperasinya.
- Role tenant custom hanya dapat melihat request miliknya secara default; akses seluruh request koperasi membutuhkan keputusan permission/policy eksplisit.
- Owner membaca seluruh request melalui repository/service owner yang eksplisit karena owner tidak mendapat bypass global tenant scope.
- Policy harus memeriksa permission **dan** kepemilikan tenant/record.
- Service layer mengulangi invariant penting untuk mencegah IDOR pada bulk/action endpoint.
- Route model binding tidak dianggap cukup sebagai tenant security.
- Owner tidak boleh memperoleh akses data operasional yang disebut dalam pesan kecuali informasi tersebut memang diketik atau dilampirkan secara sadar oleh pengaju.

### 7.5 Service dan file yang direncanakan

```text
app/Models/ProductRequest.php
app/Models/ProductRequestMessage.php
app/Models/ProductRequestAttachment.php
app/Models/ProductRequestStatusHistory.php
app/Enums/ProductRequestType.php
app/Enums/ProductRequestStatus.php
app/Enums/ProductRequestPriority.php
app/Policies/ProductRequestPolicy.php
app/Services/ProductRequestService.php
app/Services/ProductRequestMessageService.php
app/Repositories/ProductRequestRepository.php
app/Http/Controllers/ProductRequestController.php
app/Http/Controllers/ProductRequestMessageController.php
app/Http/Controllers/OwnerProductRequestController.php
app/Http/Requests/ProductRequest/*
app/Notifications/ProductRequestUpdated.php
resources/views/product-requests/*
resources/views/owner/product-requests/*
tests/Feature/ProductRequestTest.php
tests/Feature/ProductRequestTenantIsolationTest.php
tests/Feature/OwnerProductRequestTest.php
```

### 7.6 UI pengaju

Halaman minimum:

- Daftar request dengan pencarian/filter jenis/status.
- Form pengajuan.
- Detail request dan timeline percakapan.
- Form balasan dan lampiran.
- Tombol tutup/buka kembali sesuai aturan.
- Badge status dengan label Bahasa Indonesia.
- Empty state dan petunjuk informasi yang perlu disertakan.

Form pengajuan harus mengingatkan pengguna:

- Jangan mengirim password atau token.
- Hindari data pribadi yang tidak diperlukan.
- Screenshot/lampiran akan dapat dibaca tim pengelola produk.

### 7.7 UI system owner

- Inbox seluruh request.
- Filter koperasi, jenis, status, priority, assignment, dan tanggal.
- Detail request dengan percakapan publik.
- Catatan internal yang jelas berbeda secara visual.
- Aksi ubah status, priority internal, assignment, dan tandai duplikat.
- Riwayat status.
- Statistik jumlah request, waktu respons pertama, waktu penyelesaian, dan backlog.
- Tautan ke request sumber ketika status duplikat.

Tidak perlu membuat roadmap publik pada MVP. Status `planned` cukup menjadi sinyal awal; roadmap/voting dapat menjadi fase berikutnya.

### 7.8 Notifikasi

Event minimum:

- Request baru memberi notifikasi kepada owner.
- Balasan owner memberi notifikasi kepada pengaju/peserta tenant.
- Balasan tenant memberi notifikasi kepada owner/assignee.
- Perubahan status publik memberi notifikasi kepada pengaju.
- Catatan internal tidak pernah memberi notifikasi kepada tenant.

Implementasi:

- Gunakan notification database sebagai dasar.
- Email dapat ditambahkan dan dikirim melalui queue.
- Kegagalan email tidak boleh membatalkan penyimpanan request/balasan.
- Hindari memasukkan isi sensitif lengkap ke subject/log email.
- Cegah notifikasi ganda pada retry dengan event/listener yang idempotent bila diperlukan.

## 8. Checklist implementasi pusat request (`feature/product-request-center`)

### 8.1 Git dan persiapan

- [ ] Pastikan checklist system owner wajib telah selesai di `main`.
- [ ] Pastikan `main` bersih dan seluruh test lulus.
- [ ] Buat branch `feature/product-request-center` dari `main` terbaru.
- [ ] Jangan melakukan implementasi request produk langsung di `main`.
- [ ] Tetapkan apakah role tenant custom melihat request sendiri atau seluruh request koperasi.
- [ ] Tetapkan batas ukuran, jumlah, MIME, dan retensi lampiran.
- [ ] Tetapkan kanal notifikasi MVP.

### 8.2 Database dan domain

- [ ] Buat migration `product_requests`.
- [ ] Buat migration messages.
- [ ] Buat migration attachments.
- [ ] Buat migration immutable status histories.
- [ ] Tambahkan foreign key, delete behavior, unique constraint, dan index.
- [ ] Buat enum type/status/priority.
- [ ] Buat model dan relasi.
- [ ] Terapkan tenant scope secara fail-closed.
- [ ] Buat generator nomor tiket yang aman terhadap concurrency.
- [ ] Tambahkan permission ke `PermissionCatalog`.
- [ ] Tambahkan permission default yang sesuai ke template admin primer.
- [ ] Pastikan super admin tidak otomatis menjadi pengelola request owner kecuali diputuskan kemudian.

### 8.3 Backend

- [ ] Buat request validation pengajuan.
- [ ] Buat request validation balasan/lampiran.
- [ ] Buat policy per aksi dan per record.
- [ ] Buat repository tenant dan owner dengan scope eksplisit.
- [ ] Buat service transaction untuk membuat request + history awal.
- [ ] Buat service balasan + update `last_activity_at`.
- [ ] Buat transition service status.
- [ ] Buat aksi duplicate dan relasi sumber.
- [ ] Buat catatan internal owner.
- [ ] Terapkan transactional file storage.
- [ ] Buat endpoint download attachment terotorisasi.
- [ ] Buat event/listener/notification.
- [ ] Tambahkan audit aksi owner.

### 8.4 Frontend

- [ ] Tambahkan menu Request Produk untuk actor tenant yang memiliki permission.
- [ ] Tambahkan menu Request Produk pada navigasi owner.
- [ ] Buat daftar/filter/paginasi tenant.
- [ ] Buat form pengajuan yang aksesibel.
- [ ] Buat detail/timeline dan balasan.
- [ ] Buat inbox/filter owner.
- [ ] Buat panel triase owner.
- [ ] Bedakan catatan internal dan balasan publik secara tegas.
- [ ] Buat empty, loading, validation, forbidden, dan error states.
- [ ] Pastikan UI mobile/responsive.

### 8.5 Pengujian

- [ ] Admin primer dapat membuat dan melihat request koperasinya.
- [ ] Role tanpa permission tidak dapat mengakses fitur.
- [ ] Anggota dengan permission dapat membuat request sesuai policy.
- [ ] Tenant A tidak dapat melihat, membalas, atau mengunduh lampiran Tenant B.
- [ ] ID yang ditebak tetap menghasilkan 403/404 yang aman.
- [ ] Owner dapat melihat dan menanggapi seluruh request.
- [ ] Owner dapat membuat catatan internal.
- [ ] Tenant tidak dapat mendeteksi isi maupun keberadaan catatan internal.
- [ ] Transisi status ilegal ditolak.
- [ ] History status selalu tercatat dan tidak dapat diedit.
- [ ] Nomor tiket tetap unik pada request paralel.
- [ ] File dengan MIME/ukuran tidak valid ditolak.
- [ ] File storage dibersihkan jika transaction database gagal.
- [ ] Row database tetap konsisten jika operasi file gagal.
- [ ] Download attachment membutuhkan otorisasi.
- [ ] Notifikasi tidak terkirim ke tenant yang salah.
- [ ] Kegagalan queue/email tidak membatalkan request.
- [ ] Statistik owner tidak membocorkan isi catatan internal atau data personal lain.
- [ ] Seluruh regression test tenant isolation dan permission tetap lulus.

### 8.6 Penyelesaian branch

- [ ] Jalankan formatter.
- [ ] Jalankan `npm run build`.
- [ ] Jalankan test khusus pusat request.
- [ ] Jalankan seluruh `php artisan test`.
- [ ] Tinjau migration rollback pada database uji.
- [ ] Tinjau index dan query list dengan data representatif.
- [ ] Tinjau seluruh diff untuk secret/path lokal.
- [ ] Perbarui dokumentasi pengguna dan panduan singkat.
- [ ] Siapkan ringkasan perubahan sebelum branch digabung ke `main`.

## 9. Urutan implementasi yang direkomendasikan

### Fase A — fondasi actor di `main`

1. Role/model helper.
2. Middleware dan route owner kosong.
3. Proteksi UserService/UserPolicy/form role.
4. Provisioning command.
5. Test identity, authorization, dan regression tenant.
6. Commit ke `main` setelah quality gate lulus.

### Fase B — dashboard agregat di `main`

1. Definisi metrik dan privacy rule.
2. Repository query agregat.
3. Service/cache.
4. Controller/request/routes.
5. View dan Chart.js.
6. Test kebocoran data dan performa.
7. Commit ke `main` setelah quality gate lulus.

### Fase C — health dan storage di `main`

1. Adapter health check.
2. Scheduler heartbeat.
3. Metadata backup/deployment.
4. Pengukuran storage dan backfill ukuran file.
5. Sanitasi output dan audit.
6. Test failure/timeout.
7. Commit ke `main` setelah quality gate lulus.

### Fase D — pusat request pada branch baru

1. Buat `feature/product-request-center` dari `main` terbaru.
2. Migration/model/enum.
3. Permission/policy/service/repository.
4. UI tenant.
5. UI owner.
6. Lampiran dan notifikasi.
7. Statistik request pada dashboard owner.
8. Security/regression test.
9. Review sebelum digabung ke `main`.

## 10. Kriteria penerimaan akhir

### System owner

- [x] Akun owner merupakan actor global yang valid tanpa `koperasi_id`.
- [x] Owner mempunyai dashboard tersendiri.
- [x] Owner dapat melihat ringkasan global dan per koperasi dalam bentuk agregat.
- [x] Owner dapat melihat kesehatan sistem dan penggunaan storage.
- [x] Owner tidak dapat membuka atau memutasi record operasional tenant.
- [x] Owner tidak dapat memperoleh data personal melalui grafik, response, export, cache, atau error.
- [x] Super admin dan tenant tidak dapat mengakses halaman owner.
- [x] Akun owner tidak dapat dikelola melalui UI pengguna biasa.
- [x] Semua akses penting owner tercatat.
- [x] Seluruh test lama dan baru lulus.

### Pusat request produk

- [ ] Pengguna tenant yang berhak dapat mengajukan dan memantau request.
- [ ] Isolasi antar-koperasi berlaku pada request, message, attachment, dan notifikasi.
- [ ] Owner dapat melakukan triase dan percakapan publik.
- [ ] Catatan internal tidak pernah bocor kepada tenant.
- [ ] Semua perubahan status mempunyai history.
- [ ] Lampiran tervalidasi dan hanya dapat diunduh oleh pihak berwenang.
- [ ] Statistik request tampil pada dashboard owner tanpa membuka data yang tidak diperlukan.
- [ ] Seluruh test lama dan baru lulus sebelum merge.

## 11. Pekerjaan lanjutan di luar MVP

Item berikut tidak perlu menghambat MVP:

- MFA/passkey owner.
- IP allowlist owner.
- Integrasi error tracker eksternal.
- Integrasi monitoring host/container.
- Roadmap publik.
- Voting request fitur.
- SLA otomatis dan eskalasi.
- Knowledge base/FAQ dari request yang sudah selesai.
- Anonimisasi data analitik historis.
- Tabel snapshot/data warehouse khusus jika volume data berkembang besar.
- Read replica atau akun database read-only khusus proses analitik.
