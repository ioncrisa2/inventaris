# Checklist Implementasi Mini Multi-Tenancy v2.2

## Status dokumen

- Sumber kebutuhan: `mini_multi_tenancy_codex_spec_v2.2.md`
- Tanggal audit proyek: 29 Juli 2026
- Target proyek: Laravel 13.8, PHP 8.4, Blade, Bootstrap 5, Spatie Laravel Permission 8.3
- Pola aplikasi saat ini: Controller → Service → Repository, Form Request, Policy, Pest
- Status: perencanaan saja; belum ada kode aplikasi yang diubah untuk fitur multi-tenancy
- Cara memakai: kerjakan berurutan, centang hanya setelah implementasi, automated test, dan pemeriksaan regresi fase tersebut lulus

Dokumen ini bukan salinan mentah spesifikasi. Checklist sudah disesuaikan dengan source code dan worktree proyek saat ini. Audit pada spesifikasi dibuat dari ZIP yang lebih lama; proyek sekarang sudah mempunyai modul Hari Libur, sinkronisasi Nager.Date, hari operasional, dan laporan Penyusutan. Semua modul baru itu ikut masuk lingkup multi-tenancy.

## Traceability revisi v2.2

| Revisi | Cakupan di checklist |
|---|---|
| R1 — konsistensi `koperasi_id` dengan parent | Fase 1 backfill/invariant dan Fase 3 assignment parent-bound |
| R2 — scoped binding nested route | Fase 3 route model binding dan nested child |
| R3 — redirect dan dashboard role platform | Fase 2 route dan dashboard role-aware |
| R4 — expired tanpa payment self-service | Fase 5 middleware subscription |
| R5 — penempatan middleware subscription | Fase 5 route operasional vs route pengecualian |
| R6 — onboarding atomik | Fase 5 onboarding koperasi |
| R7 — test dan catatan implementasi | Fase 8 automated test, regression, dan rollout |
| R8 — backup platform system owner | Fase 7 backup dan restore terkontrol |

## Keputusan arsitektur yang tidak boleh berubah

- [ ] Gunakan satu aplikasi, satu database, satu schema, dan `koperasi_id` sebagai pemilik data tenant.
- [ ] Pertahankan Blade dan Bootstrap; jangan rewrite ke Vue/Inertia.
- [ ] Jangan memasang package tenancy atau Spatie teams.
- [ ] Gunakan Spatie Laravel Permission untuk role/permission.
- [ ] Gunakan Spatie Laravel Activitylog sebagai satu-satunya sumber audit utama.
- [ ] Pisahkan role platform `system_owner` dan `super_admin` dari role tenant.
- [ ] Hanya `system_owner` yang memperoleh bypass global melalui Gate.
- [ ] Kelola subscription dan pembayaran secara manual dari panel platform; tenant tidak mempunyai payment self-service.
- [ ] Gunakan policy dan query scope secara bersamaan.
- [ ] Jangan percaya `koperasi_id`, role, foreign ID, parent ID, path, atau nama file yang dikirim browser.
- [ ] Turunkan `koperasi_id` record parent-bound dari parent yang sudah di-scope dan tervalidasi.
- [ ] Gunakan storage private untuk dokumen sensitif, bukti pembayaran, dan archive backup.
- [ ] Backup hanya dapat dikelola `system_owner` dan mencakup database serta upload yang di-allowlist.
- [ ] Pertahankan business logic inventaris, penggajian, hari operasional, dan penyusutan yang tidak perlu diubah.

## Temuan audit kondisi proyek saat ini

| Area | Kondisi aktual | Implikasi pekerjaan |
|---|---|---|
| Role | Seeder masih membuat `Admin` dan `Staff` | Harus dimigrasikan secara eksplisit ke lima role target |
| Gate | Belum ada `Gate::before` | Tambahkan bypass hanya untuk `system_owner` |
| User | Belum mempunyai `koperasi_id`; profile dapat mengganti `unit_kerja_id` sendiri | Tambahkan tenant ownership dan hentikan perpindahan unit lintas tenant lewat profile |
| Role assignment | Controller memuat seluruh role dan request hanya memakai `exists` | Buat allowlist role terpusat berdasarkan actor |
| Route | Semua fitur berada di satu grup `auth` | Pisahkan `/system`, `/platform`, route tenant operasional, dan route tenant yang tetap tersedia saat expired |
| Schema | Belum ada tabel `koperasi` atau kolom `koperasi_id` | Perlu migration additive, backfill, validasi invariant, lalu constraint |
| Unique key | `nama_unit`, `nik`, `kode_barang`, `pengaturan.key`, `hari_libur.tanggal`, dan `karyawan.nomor_ktp` masih global | Ubah key bisnis tenant menjadi composite unique dan putuskan kebijakan `nomor_ktp` |
| Policy | Policy resource hanya memeriksa permission actor, bukan target dan tenant | Ubah signature dan pemeriksaan target untuk semua resource tenant |
| Query | Repository dan raw query dashboard/laporan masih global | Semua entry point query harus menerima actor/tenant context |
| Bulk action | User, barang, karyawan, unit, komponen gaji, transaksi gaji, hari libur, dan barcode massal masih dapat meng-query ID global | Scope sebelum `whereKey`/`whereIn`, validasi jumlah hasil, lalu authorisasi tiap target |
| Nested resource | Child sudah dicek terhadap parent di beberapa controller, tetapi parent dan child masih di-bind global | Tambahkan scoped binding dan resolve child melalui relasi parent tenant-scoped |
| Dashboard | `DashboardRepository` memakai Eloquent dan `DB::table` global | Tambahkan constraint tenant pada root query, join, subquery, dan agregasi |
| Cache | `DashboardCache` hanya mempunyai generation key global | Namespace dan invalidasi per tenant, platform, dan system |
| Pengaturan | Static `get`/`set` dan mutex generator kode barang masih global | Ubah API pengaturan dan lock generator agar tenant-aware |
| Storage | Upload sekarang tersebar di folder global | Migrasikan path ke prefix `koperasi/{id}` dan amankan seluruh akses |
| Hari Libur | Modul baru mempunyai CRUD tenant user, tetapi tabel/query masih global | Perlakukan sebagai data tenant agar satu koperasi tidak mengubah kalender/payroll koperasi lain |
| Penyusutan | Laporan baru memakai query barang yang sama seperti inventaris | Wajib ikut scope pada layar, cetak, dan export |
| Audit | Activitylog belum terpasang | Pasang package, custom Activity model, dan event bisnis terpilih |
| Subscription | Belum ada schema, middleware, scheduler, atau panel | Bangun modul platform baru tanpa self-service tenant |
| Backup | Belum ada disk, metadata, job, command, scheduler, atau panel | Bangun orchestration backup dan restore terkontrol |
| Testing | Helper test masih membuat role `Admin`/`Staff`; terdapat 262 test declaration dan banyak fixture single-tenant | Refactor fixture menjadi tenant-aware tanpa membuang regression coverage |
| Worktree | Banyak perubahan lokal belum di-commit, terutama Hari Libur, penggajian, penyusutan, dan UI | Implementasi nanti harus dimulai dari kondisi terbaru dan tidak menimpa pekerjaan lokal |

## Keputusan yang harus dikunci sebelum implementasi

Jangan mulai migrasi data produksi sebelum nilai berikut ditentukan. Default yang disarankan ditulis langsung pada tiap butir.

- [ ] Tentukan email akun `system_owner` pertama secara eksplisit; jangan menebak dari role `Admin`.
- [ ] Tentukan email akun `super_admin` pertama secara eksplisit dan pastikan berbeda dari email system owner.
- [ ] Tentukan kode, nama, slug, serta data tenant default untuk seluruh data existing.
- [ ] Tetapkan Hari Libur sebagai tenant-owned. Ini default yang disarankan karena tenant saat ini dapat menambah, mengubah, menghapus, dan menyinkronkan hari libur; kalender tersebut juga memengaruhi absensi dan penggajian.
- [ ] Putuskan kebijakan `karyawan.nomor_ktp`. Default yang disarankan adalah unique per tenant agar tidak membocorkan keberadaan pegawai di tenant lain dan tidak memblokir migrasi data antar-tenant. Bila harus global, alasan bisnis dan perilaku error harus didokumentasikan.
- [ ] Jadikan `unit_kerja.kode` unique per tenant saat terisi. Generator kode barang bergantung pada kode tersebut dan kode ganda dalam tenant akan ambigu.
- [ ] Tentukan status subscription awal saat onboarding: `trial` atau `pending_payment`.
- [ ] Tentukan panjang trial, grace period, dan transisi akhir dari `grace_period` ke `suspended` atau `expired`.
- [ ] Tentukan kebijakan akses data untuk subscription `cancelled`.
- [ ] Tentukan apakah `suspended` boleh melihat seluruh modul read-only atau hanya laporan/export.
- [ ] Tentukan siapa yang boleh melihat audit tenant: hanya platform atau juga `admin_primer`.
- [ ] Tentukan retensi audit per kategori sesuai kebutuhan hukum/operasional.
- [ ] Tentukan disk backup production yang benar-benar off-site atau berbeda media fisik dari aplikasi.
- [ ] Tentukan direktori upload public existing yang tetap dipertahankan sebagai public. Dokumen sensitif tidak boleh dipindah ke public.
- [ ] Tentukan driver database production dan lokasi executable dump yang didukung.
- [ ] Tentukan queue connection dan queue worker khusus `backup`.
- [ ] Tentukan cache store production yang mendukung atomic lock dan kebutuhan `onOneServer`.

## Urutan eksekusi yang disarankan

Urutan di spesifikasi perlu sedikit diperketat untuk deployment nyata. Role platform membutuhkan `users.koperasi_id`, sedangkan data existing belum mempunyai tenant. Karena itu, migration schema additive harus tersedia sebelum akun existing dipindahkan ke role baru. Jangan mengaktifkan route baru atau constraint non-null di tengah data yang belum dibackfill.

Alur aman:

1. Kunci keputusan dan baseline.
2. Tambahkan schema tenant secara nullable serta fondasi model.
3. Jalankan bootstrap/backfill tervalidasi.
4. Aktifkan role, route, policy, dan isolasi query.
5. Baru kencangkan constraint non-null/composite unique.
6. Lanjutkan dashboard/storage, subscription, audit, backup, dan hardening.

## Fase 0 — Baseline dan pagar perubahan

### Baseline teknis

- [ ] Catat commit/branch dasar dan daftar perubahan lokal yang harus dipertahankan.
- [ ] Jalankan seluruh test existing sebelum implementasi dan simpan hasilnya sebagai baseline.
- [ ] Jalankan suite SQLite dan, bila tersedia, suite MySQL agar perilaku migration/FK tidak hanya benar di SQLite.
- [ ] Inventarisasi data produksi: jumlah user, unit, karyawan, barang, absensi, transaksi gaji, pengaturan, hari libur, dan file upload.
- [ ] Inventarisasi row yatim, foreign key tidak valid, unique collision potensial, dan data parent-child yang tidak konsisten.
- [ ] Pastikan backup database dan file manual yang dapat dipulihkan tersedia sebelum migration produksi pertama.
- [ ] Pastikan registration tetap nonaktif.
- [ ] Pastikan tidak ada rewrite frontend atau refactor business logic yang diselipkan ke pekerjaan tenancy.

### Kontrak implementasi

- [ ] Tetapkan `SystemRoles` sebagai satu sumber nama role.
- [ ] Tetapkan `KoperasiContext` sebagai satu sumber klasifikasi actor dan tenant aktif.
- [ ] Tetapkan trait `BelongsToKoperasi` untuk relasi dan explicit query scope model root.
- [ ] Tetapkan helper redirect role-aware sebagai satu sumber landing pasca-login.
- [ ] Tetapkan service/activity logger terpusat untuk audit manual dan identitas causer asli saat impersonation.
- [ ] Dokumentasikan bahwa platform role dengan `koperasi_id = null` bukan tenant dan tidak boleh masuk dashboard tenant.

**Gate selesai fase 0**

- [ ] Baseline test hijau atau kegagalan existing sudah dicatat terpisah.
- [ ] Semua keputusan kritis migrasi data sudah mempunyai nilai eksplisit.
- [ ] Tidak ada asumsi email akun platform yang masih ditebak.

## Fase 1 — Fondasi schema tenant dan migrasi data existing

### Tabel dan model `koperasi`

- [ ] Tambahkan tabel/model `koperasi` dengan `kode`, `nama`, `slug`, profil kontak, `logo_path`, dan `status_operasional`.
- [ ] Terapkan unique global untuk `kode` dan `slug`.
- [ ] Gunakan status `active`, `suspended`, dan `archived`.
- [ ] Tambahkan relasi ke user dan seluruh root tenant-owned.
- [ ] Gunakan `restrictOnDelete` untuk seluruh data yang masih dimiliki tenant.
- [ ] Hindari hard delete tenant berisi data; gunakan suspend/archive sebagai alur normal.

### Kolom tenant ownership

- [ ] Tambahkan `users.koperasi_id` nullable dan pertahankan `unit_kerja_id` nullable.
- [ ] Tambahkan `koperasi_id` nullable lebih dulu pada `unit_kerja`.
- [ ] Tambahkan `koperasi_id` nullable lebih dulu pada `karyawan`.
- [ ] Tambahkan `koperasi_id` nullable lebih dulu pada `barang`.
- [ ] Tambahkan `koperasi_id` nullable lebih dulu pada `absensi`.
- [ ] Tambahkan `koperasi_id` nullable lebih dulu pada `komponen_gaji`.
- [ ] Tambahkan `koperasi_id` nullable lebih dulu pada `transaksi_gaji`.
- [ ] Tambahkan `koperasi_id` nullable lebih dulu pada `pengaturan`.
- [ ] Tambahkan `koperasi_id` nullable lebih dulu pada `hari_libur`.
- [ ] Jangan menduplikasi `koperasi_id` pada `riwayat_kondisi_barang`, `foto_barang`, `dokumen_barang`, `dokumen_karyawan`, dan `transaksi_gaji_detail`.
- [ ] Tambahkan index `koperasi_id + created_at` pada root tenant-owned.
- [ ] Tambahkan index tenant sesuai filter aktual: unit kerja, status karyawan, tanggal absensi, periode gaji, tanggal hari libur, kategori barang, dan key pengaturan.

### Model dan relasi

- [ ] Tambahkan relasi `koperasi` pada User dan semua model root.
- [ ] Pasang `BelongsToKoperasi` pada seluruh model root tenant-owned, termasuk HariLibur.
- [ ] Tambahkan `koperasi_id` ke assignment model yang diperlukan tanpa pernah mengisinya dari request mentah.
- [ ] Pastikan eager load default User tidak menyebabkan akses unit lintas tenant.
- [ ] Pastikan platform user selalu mempunyai `unit_kerja_id = null`.
- [ ] Pastikan user tenant selalu mempunyai `koperasi_id` dan unit yang dipilih berasal dari koperasi yang sama.

### Bootstrap dan backfill

- [ ] Buat command idempotent untuk bootstrap data existing dengan parameter koperasi default, email system owner, dan email super admin.
- [ ] Tolak command bila salah satu email tidak ditemukan.
- [ ] Tolak command bila kedua email sama.
- [ ] Tolak command bila bootstrap sudah pernah selesai.
- [ ] Buat tenant default dalam transaction.
- [ ] Backfill `unit_kerja`, `komponen_gaji`, `pengaturan`, dan Hari Libur ke tenant default.
- [ ] Backfill `karyawan` dan `barang` dari `unit_kerja.koperasi_id`, bukan assignment independen.
- [ ] Backfill `absensi` dan `transaksi_gaji` dari `karyawan.koperasi_id`.
- [ ] Set system owner dan super admin ke `koperasi_id = null` dan `unit_kerja_id = null`.
- [ ] Set seluruh user operasional lain ke tenant default.
- [ ] Migrasikan role `Admin`/`Staff` secara eksplisit setelah tenant user dapat dibedakan dari akun platform.
- [ ] Validasi tidak ada root row operasional dengan `koperasi_id = null`.
- [ ] Validasi tidak ada user tenant dengan `koperasi_id = null`.
- [ ] Validasi tidak ada platform user dengan `koperasi_id` atau `unit_kerja_id` terisi.
- [ ] Validasi invariant Barang → Unit Kerja.
- [ ] Validasi invariant Karyawan → Unit Kerja.
- [ ] Validasi invariant Absensi → Karyawan.
- [ ] Validasi invariant Transaksi Gaji → Karyawan.
- [ ] Validasi `atasan_langsung_id` menunjuk karyawan dalam tenant yang sama.
- [ ] Validasi `transaksi_gaji_detail.komponen_gaji_id` menunjuk komponen dalam tenant transaksi yang sama.
- [ ] Hentikan bootstrap dan rollback bila satu invariant gagal.

### Constraint setelah backfill

- [ ] Ubah `koperasi_id` root operasional menjadi non-null.
- [ ] Biarkan `users.koperasi_id` nullable hanya untuk role platform.
- [ ] Tambahkan FK `restrictOnDelete` dari root data dan user ke koperasi.
- [ ] Ganti unique `unit_kerja.nama_unit` menjadi composite `koperasi_id + nama_unit`.
- [ ] Tambahkan composite unique `koperasi_id + unit_kerja.kode` bila kode terisi.
- [ ] Ganti unique `barang.kode_barang` menjadi composite `koperasi_id + kode_barang`.
- [ ] Ganti unique `karyawan.nik` menjadi composite `koperasi_id + nik`.
- [ ] Terapkan keputusan composite/global untuk `karyawan.nomor_ktp`.
- [ ] Ganti unique `pengaturan.key` menjadi composite `koperasi_id + key`.
- [ ] Ganti unique `hari_libur.tanggal` menjadi composite `koperasi_id + tanggal`.
- [ ] Pertahankan unique email user secara global.
- [ ] Pertahankan unique periode absensi/transaksi yang berbasis parent karena parent sudah tenant-owned.
- [ ] Uji rollback migration pada database disposable.
- [ ] Uji migration pada SQLite dan MySQL/MariaDB.

### Seeder dan factory

- [ ] Tambahkan seeder tenant demo sebelum seeder unit/data operasional.
- [ ] Ubah semua seeder operasional agar mencari/membuat data dalam tenant tertentu.
- [ ] Ubah `updateOrCreate` seeder agar key pencarian menyertakan `koperasi_id`.
- [ ] Ubah bulk `upsert` AbsensiSeeder agar mengisi `koperasi_id`.
- [ ] Ubah UserSeeder agar role dan tenant eksplisit.
- [ ] Jangan gunakan demo seeder sebagai bootstrap production.
- [ ] Tambahkan factory Koperasi.
- [ ] Tambahkan state factory User untuk system owner, super admin, admin primer, operator, dan viewer.
- [ ] Ubah helper test `adminUser`/`staffUser` menjadi helper role target yang selalu membuat tenant saat dibutuhkan.
- [ ] Perbarui `SeederDataset` untuk menghitung tenant dan tabel platform baru.

**Gate selesai fase 1**

- [ ] Seluruh data existing mempunyai ownership valid.
- [ ] Tidak ada mismatch parent-child.
- [ ] Constraint composite bekerja di dua tenant berbeda.
- [ ] Bootstrap kedua ditolak tanpa mengubah data.
- [ ] Seluruh seeder local/testing tetap idempotent.

## Fase 2 — Role platform, user protection, route, dan redirect

### Role dan permission

- [ ] Buat role `system_owner`, `super_admin`, `admin_primer`, `operator_primer`, dan `viewer_primer`.
- [ ] Jangan mengandalkan inheritance role; sync permission masing-masing secara eksplisit.
- [ ] Tambahkan permission platform untuk koperasi, package, subscription, invoice, payment, analytics, dan read-all tenant data.
- [ ] Tambahkan permission teknis system dashboard, audit security, queue, tenant monitor, impersonation, dan backup bila diperlukan oleh policy/UI.
- [ ] Jangan berikan permission teknis otomatis kepada `super_admin`.
- [ ] Tambahkan `Gate::before` yang hanya mengembalikan true untuk `system_owner`.
- [ ] Pastikan Gate bypass tidak melewati flow wajib alasan/audit untuk impersonation, support, backup, dan tindakan destruktif.
- [ ] Tandai `system_owner` dan `super_admin` sebagai protected system roles.
- [ ] Sembunyikan role protected dari panel role umum.
- [ ] Tolak rename, permission update, single delete, dan bulk delete role protected di service.
- [ ] Catat perubahan role dan permission ke activity log saat fase audit aktif.

### Allowlist assignment user

- [ ] Buat satu service/kelas sumber allowlist role berdasarkan actor.
- [ ] System owner dapat memilih semua role.
- [ ] Super admin hanya dapat memilih role tenant.
- [ ] Admin primer hanya dapat memilih role tenant dan tenant tujuan selalu tenant actor.
- [ ] Terapkan allowlist yang sama pada controller, Blade, Form Request, dan service.
- [ ] Gunakan validasi allowlist, bukan sekadar `exists` pada tabel role.
- [ ] Jangan izinkan request mengirim `koperasi_id` bebas untuk admin primer.
- [ ] Untuk actor platform, validasi tenant tujuan aktif dan unit kerja milik tenant tersebut.
- [ ] Pastikan update role tidak dapat dipakai untuk memindahkan user antartenant tanpa flow platform khusus.

### Policy dan perlindungan akun

- [ ] Ubah UserPolicy agar `view`, `update`, dan `delete` menerima actor serta target.
- [ ] System owner dapat mengelola target sesuai flow teknis, tetapi last-owner tetap dilindungi di service.
- [ ] Super admin tidak dapat melihat detail keamanan atau mengubah system owner.
- [ ] Tenant actor tidak dapat melihat atau mengelola role platform maupun user tenant lain.
- [ ] Lindungi system owner terakhir dari delete dan demotion.
- [ ] Lindungi super admin terakhir dari delete dan demotion tanpa pengganti aktif.
- [ ] Lindungi admin primer terakhir dalam setiap tenant agar tenant tidak tersisa tanpa admin.
- [ ] Terapkan perlindungan yang sama pada bulk action.
- [ ] Pertahankan larangan menghapus akun sendiri.
- [ ] Pastikan platform account selalu `koperasi_id = null`.
- [ ] Pastikan tenant account selalu `koperasi_id` terisi.

### Profile

- [ ] Hapus kemampuan user tenant memindahkan dirinya ke unit kerja tenant lain melalui profile.
- [ ] Pilih salah satu: jadikan unit kerja read-only di profile atau validasi hanya unit dalam tenant sendiri.
- [ ] Jangan tampilkan daftar unit global pada profile platform user.
- [ ] Pastikan perubahan email tetap mengikuti unique global.
- [ ] Catat perubahan profil sensitif dan password tanpa menyimpan password/token dalam log.

### Route dan dashboard role-aware

- [ ] Tambahkan dashboard eksplisit `/system` untuk system owner.
- [ ] Tambahkan dashboard eksplisit `/platform` untuk system owner dan super admin.
- [ ] Pertahankan `/dashboard` hanya untuk user tenant.
- [ ] Tambahkan grup `/system/*` dengan middleware role `system_owner`.
- [ ] Tambahkan grup `/platform/*` dengan middleware role `system_owner|super_admin`.
- [ ] Tambahkan helper landing terpusat untuk login dan redirect internal.
- [ ] System owner diarahkan ke `/system`.
- [ ] Super admin diarahkan ke `/platform`.
- [ ] User tenant diarahkan ke `/dashboard`.
- [ ] Tolak atau redirect platform user yang membuka dashboard tenant.
- [ ] Tolak direct URL tenant user ke `/platform/*` dan `/system/*`.
- [ ] Pisahkan menu system, platform, dan tenant dalam `NavigationMenu`.
- [ ] Ikuti aturan UI/navigation pada `CLAUDE.md` untuk halaman baru.
- [ ] Jangan menaruh menu platform di grup domain tenant.

**Gate selesai fase 2**

- [ ] Super admin tidak dapat mengelola system owner.
- [ ] Admin primer tidak dapat melihat atau memberikan role platform.
- [ ] Last owner, last super admin, dan last admin primer tenant terlindungi.
- [ ] Landing pasca-login benar untuk ketiga jenis user.
- [ ] Route system/platform menolak role yang salah melalui direct URL.

## Fase 3 — Isolasi query, parent-child, validation, dan bulk action

### KoperasiContext dan explicit scope

- [ ] Implementasikan klasifikasi system owner, super admin, platform user, dan tenant user.
- [ ] `tenantId` harus gagal jelas bila tenant user tidak mempunyai `koperasi_id`.
- [ ] Implementasikan `forKoperasi` dan `accessibleBy` pada model root.
- [ ] Jangan menggunakan global scope berbasis `auth()` sebagai satu-satunya pengaman.
- [ ] Teruskan actor atau tenant context dari controller/service ke repository secara eksplisit.
- [ ] Untuk platform actor, bedakan permission read-all dari kemampuan create/update pada tenant tertentu.
- [ ] Jangan membiarkan data operasional platform actor dibuat dengan `koperasi_id = null`.

### Repository yang wajib diperbarui

- [ ] Scope `UnitKerjaRepository`, termasuk ordered list, find, paginate, dan bulk delete.
- [ ] Scope `KaryawanRepository`, termasuk atasan options, find-for-update, search, dan bulk delete.
- [ ] Scope `BarangRepository`, termasuk count, kode exists, list, dan bulk delete.
- [ ] Scope `AbsensiRepository`, termasuk calendar, lookup tanggal, dan upsert.
- [ ] Scope `KomponenGajiRepository`, termasuk pilihan form dan bulk update/delete.
- [ ] Scope `TransaksiGajiRepository`, termasuk list per karyawan, conflicting period, dan lock-for-update.
- [ ] Scope `HariLiburRepository`, termasuk daftar tahun, lookup tanggal, batch rentang, dan bulk delete.
- [ ] Scope `UserRepository`.
- [ ] Scope `DashboardRepository`.
- [ ] Scope seluruh query root di `LaporanRepository`.
- [ ] Pastikan repository child hanya dipanggil melalui parent tenant-scoped.
- [ ] Biarkan RoleRepository global, tetapi batasi akses modulnya lewat role/policy.

### Query langsung di luar repository

- [ ] Hapus atau scope query global bulk user di UserController.
- [ ] Hapus atau scope query global bulk transaksi gaji di TransaksiGajiController.
- [ ] Scope query barcode massal di BarangController.
- [ ] Scope query tahun bulk Hari Libur di HariLiburController.
- [ ] Scope query KomponenGaji di StoreTransaksiGajiRequest.
- [ ] Audit seluruh Eloquent static query, `DB::table`, `whereKey`, `whereIn`, `pluck`, count, sum, export, dan scheduler.
- [ ] Tambahkan static check atau test yang memudahkan menemukan entry point query tenant baru yang lupa di-scope.

### Assignment parent-bound

- [ ] Barang: resolve Unit Kerja dalam tenant tujuan lalu turunkan `koperasi_id`.
- [ ] Karyawan: resolve Unit Kerja dalam tenant tujuan lalu turunkan `koperasi_id`.
- [ ] Karyawan: validasi atasan langsung berasal dari tenant yang sama.
- [ ] Absensi: resolve Karyawan tenant-scoped lalu turunkan `koperasi_id`.
- [ ] Transaksi Gaji: resolve Karyawan tenant-scoped lalu turunkan `koperasi_id`.
- [ ] Transaksi Gaji Detail: validasi Komponen Gaji berasal dari tenant transaksi.
- [ ] User tenant: validasi Unit Kerja berasal dari `koperasi_id` user.
- [ ] Saat parent berubah, hitung ulang `koperasi_id` dari parent baru dalam transaction.
- [ ] Tolak request yang mencoba membawa `koperasi_id` sebagai sumber kebenaran kedua.

### Route model binding dan nested child

- [ ] Pastikan root route binding tenant-owned menghasilkan 404 untuk ID tenant lain.
- [ ] Scope route `karyawan/{karyawan}` pada Absensi.
- [ ] Scope route `transaksi-gaji/karyawan/{karyawan}`.
- [ ] Scope semua route resource Barang, Karyawan, Unit Kerja, Komponen Gaji, Transaksi Gaji, User, dan Hari Libur.
- [ ] Aktifkan scoped binding pada nested Dokumen Barang.
- [ ] Aktifkan scoped binding pada nested Foto Barang.
- [ ] Aktifkan scoped binding pada nested Dokumen Karyawan.
- [ ] Resolve child melalui relasi parent walaupun scoped binding sudah aktif.
- [ ] Pastikan parent sendiri di-resolve melalui tenant scope sebelum child.
- [ ] Terapkan pola yang sama pada Riwayat Kondisi Barang.
- [ ] Jangan query child dengan primary key global.

### Validation tenant-aware

- [ ] Scope `unit_kerja_id` pada User request.
- [ ] Scope `unit_kerja_id` pada Profile request bila field tetap editable.
- [ ] Scope `unit_kerja_id` pada Karyawan request.
- [ ] Scope `atasan_langsung_id` pada Karyawan request.
- [ ] Scope `unit_kerja_id` pada Barang request.
- [ ] Scope `karyawan_id` dan Komponen Gaji pada Transaksi Gaji request.
- [ ] Scope barcode `barang_ids`.
- [ ] Scope seluruh ID pada bulk action.
- [ ] Scope filter karyawan/unit pada seluruh laporan.
- [ ] Terapkan composite unique pada request Unit Kerja, Karyawan, Barang, Pengaturan, dan Hari Libur.
- [ ] Untuk actor platform, hitung tenant target lebih dulu sebelum membangun rules.
- [ ] Jangan bocorkan keberadaan ID tenant lain melalui pesan validation.

### Policy resource

- [ ] Ubah BarangPolicy agar `view/update/delete` memeriksa target tenant.
- [ ] Ubah KaryawanPolicy agar `view/update/delete` memeriksa target tenant.
- [ ] Ubah UnitKerjaPolicy agar target-aware.
- [ ] Ubah KomponenGajiPolicy agar target-aware.
- [ ] Ubah TransaksiGajiPolicy agar target-aware.
- [ ] Ubah HariLiburPolicy agar target-aware.
- [ ] Tambahkan policy atau authorization parent untuk Absensi.
- [ ] Gunakan parent policy untuk Riwayat/Foto/Dokumen child.
- [ ] Pastikan permission read-all platform tidak otomatis memberi write lintas tenant.

### Bulk action

- [ ] Scope bulk delete user sebelum mengambil target.
- [ ] Scope bulk delete unit kerja.
- [ ] Scope bulk delete karyawan.
- [ ] Scope bulk delete barang.
- [ ] Scope barcode massal.
- [ ] Scope bulk delete komponen gaji.
- [ ] Scope bulk delete transaksi gaji.
- [ ] Scope bulk delete hari libur.
- [ ] Pastikan jumlah row scoped sama dengan jumlah ID yang diminta; bila tidak, gagal atomik.
- [ ] Authorize setiap target sebelum mutasi.
- [ ] Catat satu event audit bisnis yang jelas untuk bulk action.

**Gate selesai fase 3**

- [ ] Tenant A tidak dapat membaca, mengubah, menghapus, mencetak, atau mengunduh data Tenant B.
- [ ] Cross-tenant URL dan nested child mengembalikan 404.
- [ ] Foreign ID Tenant B ditolak pada request Tenant A.
- [ ] Bulk action campuran Tenant A/B gagal tanpa mengubah data.
- [ ] Semua parent-child invariant tetap benar setelah create dan update.

## Fase 4 — Dashboard, laporan, pengaturan, storage, dan cache

### Dashboard tenant/platform/system

- [ ] Ubah DashboardService agar selalu meneruskan actor/tenant.
- [ ] Scope ringkasan inventaris raw query.
- [ ] Scope count karyawan aktif.
- [ ] Scope tren absensi.
- [ ] Scope kondisi inventaris beserta subquery riwayat terakhir.
- [ ] Scope query data barang/karyawan belum lengkap beserta `NOT EXISTS` child.
- [ ] Jangan tampilkan agregat global melalui dashboard tenant.
- [ ] Buat dashboard platform untuk metrik tenant, subscription, invoice, payment, dan pendapatan.
- [ ] Buat dashboard system untuk health, queue, scheduler, backup, storage, versi, error, dan security activity.
- [ ] Pisahkan cache key dashboard tenant, platform, dan system.

### Laporan, cetak, export, dan media

- [ ] Scope laporan Inventaris pada screen, rekap, cetak, dan Excel.
- [ ] Scope laporan Absensi pada screen, rekap, cetak, dan Excel.
- [ ] Scope laporan Kepegawaian pada screen, rekap, cetak, dan Excel.
- [ ] Scope laporan Penggajian pada screen, rekap, slip, cetak, dan Excel.
- [ ] Scope laporan Penyusutan pada screen, ringkasan, cetak, dan Excel.
- [ ] Scope daftar filter Unit Kerja dan Karyawan.
- [ ] Scope raw join rekap penggajian per unit.
- [ ] Scope subquery Transaksi Gaji Detail melalui transaksi tenant.
- [ ] Scope barcode tunggal dan massal.
- [ ] Scope QR code.
- [ ] Scope seluruh download dokumen.
- [ ] Pastikan export yang tetap tersedia saat expired hanya mengekspor tenant sendiri.
- [ ] Catat export, cetak sensitif, slip gaji, dan download dokumen sesuai kebijakan audit.

### Pengaturan dan Hari Operasional

- [ ] Ubah Pengaturan agar setiap `get`, `set`, dan batch read membutuhkan tenant.
- [ ] Pisahkan setting platform global ke config/tabel khusus; jangan memakai `koperasi_id = null` secara ambigu.
- [ ] Scope `hari_operasional` per tenant.
- [ ] Scope Hari Libur per tenant pada AbsensiService dan TransaksiGajiService.
- [ ] Scope sinkronisasi Nager.Date ke tenant actor/tenant tujuan.
- [ ] Pastikan sinkronisasi tenant A tidak membuat row atau mengubah payroll tenant B.
- [ ] Catat sinkronisasi Hari Libur dan perubahan hari operasional.

### Generator kode barang

- [ ] Hitung urutan barang per tenant.
- [ ] Scope cek collision kode per tenant.
- [ ] Gunakan mutex/lock dengan namespace koperasi.
- [ ] Scope row Pengaturan yang digunakan sebagai lock.
- [ ] Resolve Unit Kerja tenant-scoped sebelum mengambil kode unit.
- [ ] Pertahankan composite unique database sebagai perlindungan terakhir.

### Storage tenant

- [ ] Gunakan prefix `koperasi/{koperasi_id}` untuk seluruh upload tenant.
- [ ] Migrasikan `public/karyawan-foto` ke folder tenant.
- [ ] Migrasikan `public/barang-sampul` ke folder tenant.
- [ ] Migrasikan `public/barang-foto` ke folder tenant.
- [ ] Migrasikan `private/dokumen-barang` ke folder tenant.
- [ ] Migrasikan `private/dokumen-karyawan` ke folder tenant.
- [ ] Tambahkan folder private bukti pembayaran.
- [ ] Tambahkan folder logo koperasi sesuai keputusan public/private.
- [ ] Buat migration/command file yang idempotent dan memperbarui path database setelah file berhasil dipindah.
- [ ] Jangan menghapus file lama sebelum transaction/rollback strategy aman.
- [ ] Pastikan dokumen sensitif hanya dilayani controller ber-policy.
- [ ] Audit seluruh referensi URL public setelah path berubah.
- [ ] Pastikan penghapusan file tetap after-commit dan tenant-scoped.

### Cache

- [ ] Ubah `DashboardCache` agar version/generation key menerima namespace tenant/platform/system.
- [ ] Invalidate cache tenant yang berubah.
- [ ] Invalidate agregasi platform saat data tenant berubah.
- [ ] Jangan invalidate seluruh tenant bila tidak perlu.
- [ ] Pastikan impersonation tidak memakai cache tenant sebelumnya.
- [ ] Pastikan scheduler dan queue tidak bergantung pada `auth()` untuk memilih namespace.

**Gate selesai fase 4**

- [ ] Statistik, laporan, file, pengaturan, kalender, generator kode, dan cache tidak tercampur antartenant.
- [ ] Penyusutan dan Hari Libur mempunyai test isolasi khusus.
- [ ] Semua file sensitif melewati authorization.
- [ ] Migrasi path file existing dapat dijalankan ulang dengan aman.

## Fase 5 — Subscription manual, pembayaran, dan onboarding

### Schema dan model platform

- [ ] Tambahkan `subscription_packages`.
- [ ] Tambahkan `subscriptions` dengan snapshot harga, interval, dan durasi.
- [ ] Tambahkan `invoices` dengan nomor invoice unique global dan tenant ownership.
- [ ] Tambahkan `payments` dengan status verification.
- [ ] Gunakan decimal yang cukup untuk seluruh nominal.
- [ ] Tambahkan FK, index status/tanggal, cast, relasi, dan enum/value object terpusat.
- [ ] Jangan hard-code harga atau durasi di controller.
- [ ] Jangan mengaktifkan tenant dengan mengubah role user.

### Service/repository/policy/UI platform

- [ ] Tambahkan repository dan service untuk Koperasi, Package, Subscription, Invoice, Payment, dan Analytics.
- [ ] Tambahkan policy target-aware untuk seluruh resource platform.
- [ ] Tambahkan Form Request yang membatasi actor, status transition, nominal, dan file.
- [ ] Tambahkan controller/view di `/platform/*`.
- [ ] System owner dapat membuka area bisnis platform.
- [ ] Super admin hanya memperoleh capability bisnis yang diberikan.
- [ ] Tenant user tidak dapat melihat route atau menu platform.
- [ ] Simpan bukti pembayaran private dan layani melalui controller terautorisasi.
- [ ] Jangan tampilkan nomor rekening/PII lengkap dalam audit.

### Verifikasi pembayaran

- [ ] Aksi verify/reject menggunakan service transaction.
- [ ] Lock payment, invoice, dan subscription terkait selama verifikasi.
- [ ] Tolak verifikasi ulang atau status transition ilegal secara idempotent.
- [ ] Verifikasi menandai payment verified.
- [ ] Verifikasi menandai invoice paid.
- [ ] Verifikasi membuat atau memperpanjang subscription.
- [ ] Perpanjangan memakai `ends_at` existing bila masih aktif agar sisa periode tidak hilang.
- [ ] Simpan actor dan timestamp.
- [ ] Buat satu audit event bisnis yang memuat invoice, nominal, tenant, old/new status, dan waktu.
- [ ] Rollback semua perubahan dan file terkait bila satu langkah gagal.

### Middleware subscription

- [ ] Daftarkan alias `tenant.subscription` di `bootstrap/app.php`.
- [ ] Terapkan hanya pada route operasional tenant.
- [ ] Jangan terapkan pada `/system/*`, `/platform/*`, login, logout, profile, status subscription, dan route export yang diizinkan.
- [ ] Bypass actor platform.
- [ ] `trial` mendapat akses penuh dengan banner.
- [ ] `active` mendapat akses penuh.
- [ ] `grace_period` mendapat akses penuh dengan warning kuat.
- [ ] `suspended` menolak write dan mengikuti kebijakan read/export yang sudah diputuskan.
- [ ] `expired` hanya dapat membuka status, profile, logout, serta export/download data sendiri yang diizinkan.
- [ ] `cancelled` mengikuti kebijakan terminasi eksplisit.
- [ ] Halaman status expired hanya memberi instruksi menghubungi administrator platform.
- [ ] Jangan membuat form pembayaran tenant.
- [ ] Pastikan request method spoofing dan endpoint AJAX tidak melewati read-only rule.

### Scheduler subscription/invoice

- [ ] Buat command harian untuk active → grace period.
- [ ] Buat command harian untuk grace period → suspended/expired.
- [ ] Buat command invoice pending → overdue.
- [ ] Buat query/notifikasi subscription yang berakhir 7 dan 30 hari.
- [ ] Daftarkan scheduler dengan timezone yang benar.
- [ ] Buat command idempotent dan aman dijalankan ulang.
- [ ] Catat transition otomatis dengan `causer = null` dan tenant target yang benar.

### Onboarding koperasi

- [ ] Sediakan flow hanya untuk system owner/super admin.
- [ ] Dalam satu transaction buat koperasi, subscription awal, dan satu admin primer.
- [ ] Snapshot package saat membuat subscription.
- [ ] Pastikan admin awal mempunyai `koperasi_id` dan role `admin_primer`.
- [ ] Jangan meninggalkan koperasi tanpa admin bila pembuatan user/role gagal.
- [ ] Rollback seluruh onboarding bila salah satu langkah gagal.
- [ ] Tangani collision kode, slug, email admin, dan package nonaktif.
- [ ] Catat event onboarding dengan tenant, actor, package, dan admin awal tanpa password.

**Gate selesai fase 5**

- [ ] Payment pending/rejected tidak mengaktifkan subscription.
- [ ] Payment verified memperpanjang subscription secara atomik.
- [ ] Tenant expired tidak dapat write atau membayar sendiri.
- [ ] Platform actor tetap dapat melakukan administrasi sesuai permission.
- [ ] Onboarding gagal tidak meninggalkan data parsial.

## Fase 6 — Activity log, monitoring, dan impersonation

### Spatie Activitylog

- [ ] Tambahkan dependency yang kompatibel dengan Laravel 13.
- [ ] Publish config dan migration package.
- [ ] Tambahkan `activity_log.koperasi_id` nullable setelah tabel koperasi tersedia.
- [ ] Tambahkan index tenant/tanggal dan log name/tanggal.
- [ ] Buat custom Activity model dengan relasi Koperasi.
- [ ] Arahkan config package ke custom model.
- [ ] Jangan membuat tabel audit custom kedua.

### Strategi logging

- [ ] Gunakan automatic dirty logging secara selektif untuk CRUD sederhana.
- [ ] Gunakan manual business event untuk aksi lintas-model.
- [ ] Gunakan log name `platform`, `security`, `support`, `subscription`, `payment`, `inventaris`, `kepegawaian`, `penggajian`, `document_access`, dan `system_backup`.
- [ ] Maknai `koperasi_id` activity sebagai tenant data yang terkena, bukan tenant actor.
- [ ] Simpan request ID, IP, user agent, reason, old/new status, invoice, nominal, dan impersonated user bila relevan.
- [ ] Jangan log password, password confirmation, remember token, auth token, isi file, credential, signed URL, atau payload gaji mentah.
- [ ] Mask PII dan informasi rekening.
- [ ] Pastikan log platform murni boleh mempunyai `koperasi_id = null`.

### Event minimum

- [ ] Login/logout berhasil dan gagal sesuai kebijakan security.
- [ ] CRUD user dan perubahan tenant/unit user.
- [ ] Perubahan role dan permission.
- [ ] CRUD akun system owner/super admin.
- [ ] CRUD data utama tenant.
- [ ] Bulk action.
- [ ] Upload/download dokumen sensitif.
- [ ] Export/cetak yang diwajibkan.
- [ ] Verifikasi/penolakan payment.
- [ ] Aktivasi/perpanjangan/suspend subscription.
- [ ] Perubahan status koperasi.
- [ ] Impersonation mulai/selesai.
- [ ] Support/emergency access dengan alasan.
- [ ] Finalisasi/pembatalan penggajian bila flow tersebut tersedia.
- [ ] Sinkronisasi Hari Libur dan perubahan hari operasional.

### Audit viewer dan retensi

- [ ] System owner dapat melihat audit global.
- [ ] Super admin hanya dapat melihat log bisnis yang diizinkan.
- [ ] Admin primer, bila fitur diberikan, hanya melihat log tenant sendiri.
- [ ] Filter UI minimal mencakup tenant, causer, log name, event, tanggal, dan subject.
- [ ] Buat command cleanup per kategori, bukan satu umur global.
- [ ] Terapkan retensi operasional 1–2 tahun.
- [ ] Terapkan retensi security/role 3–5 tahun.
- [ ] Terapkan retensi payment/subscription minimal 5 tahun.
- [ ] Catat hasil cleanup tanpa menghapus log yang masih wajib disimpan.

### Impersonation

- [ ] Hanya system owner yang dapat memulai impersonation.
- [ ] Wajib meminta alasan.
- [ ] Tolak nested impersonation.
- [ ] Simpan system owner asli, user target, tenant, alasan, waktu, dan IP.
- [ ] Tampilkan banner permanen selama impersonation.
- [ ] Sediakan aksi selesai yang selalu tercatat.
- [ ] Pertahankan system owner asli sebagai causer activity selama session.
- [ ] Jangan menyimpan atau meminta password target.
- [ ] Hapus/rotasi context dan cache yang dapat tertinggal saat mulai/selesai.

### Monitoring system owner

- [ ] Tampilkan health aplikasi/database.
- [ ] Tampilkan failed jobs.
- [ ] Tampilkan heartbeat scheduler terakhir.
- [ ] Tampilkan storage per tenant.
- [ ] Tampilkan versi aplikasi.
- [ ] Tampilkan error terbaru tanpa membocorkan secret.
- [ ] Tampilkan security activity.
- [ ] Integrasikan status backup pada dashboard system.

**Gate selesai fase 6**

- [ ] Actor, target tenant, subject, event, dan waktu dapat ditelusuri.
- [ ] Tenant tidak dapat melihat log tenant lain.
- [ ] Impersonation selalu beralasan dan causer asli tidak hilang.
- [ ] Password/token/credential tidak muncul di properties.

## Fase 7 — Backup platform dan restore terkontrol

### Konfigurasi dan storage

- [ ] Tambahkan `config/platform_backup.php`.
- [ ] Tambahkan environment contract untuk disk, timezone, jadwal, retensi, temporary path, dan include list.
- [ ] Tambahkan disk `backups` private pada `config/filesystems.php`.
- [ ] Development boleh memakai local private disk.
- [ ] Production wajib memakai target terpisah/off-site.
- [ ] Jangan memasukkan `.env`, source, `.git`, vendor, node_modules, log, cache, session, temporary backup, atau backup final ke archive.
- [ ] Jangan membuat enkripsi custom; gunakan encryption at rest dari provider/infrastruktur.

### Allowlist file aktual proyek

- [ ] Sertakan private upload tenant `dokumen-barang`.
- [ ] Sertakan private upload tenant `dokumen-karyawan`.
- [ ] Sertakan private upload `payments`.
- [ ] Sertakan public user upload `karyawan-foto`.
- [ ] Sertakan public user upload `barang-sampul`.
- [ ] Sertakan public user upload `barang-foto`.
- [ ] Sertakan logo koperasi bila disimpan sebagai upload.
- [ ] Setelah migrasi path fase 4, arahkan allowlist ke prefix tenant baru.
- [ ] Jangan mengandalkan salinan seluruh folder storage.

### Metadata dan komponen

- [ ] Tambahkan `backup_runs` dengan UUID, type, status, disk/path, driver, ukuran, count, checksum, actor, timestamp, error ringkas, dan metadata.
- [ ] Gunakan status `pending`, `running`, `completed`, `failed`, dan `deleted`.
- [ ] Biarkan `triggered_by = null` untuk scheduler.
- [ ] Jangan menyimpan stack trace penuh, credential, command ber-password, atau absolute temp path sensitif.
- [ ] Tambahkan model BackupRun dan policy eksklusif system owner.
- [ ] Tambahkan PlatformBackupService.
- [ ] Tambahkan DatabaseDumper.
- [ ] Tambahkan UploadArchiveBuilder.
- [ ] Tambahkan CreatePlatformBackup job pada queue khusus.
- [ ] Tambahkan command run, prune, dan restore.
- [ ] Tambahkan SystemBackupController dan panel `/system/backups`.

### Dump database

- [ ] Deteksi driver dari connection yang dikonfigurasi.
- [ ] MySQL/MariaDB memakai `mysqldump` dengan consistent transaction dan opsi yang diperlukan.
- [ ] SQLite melakukan checkpoint dan salinan file dengan lock yang aman.
- [ ] Fail closed untuk driver yang belum didukung.
- [ ] Gunakan Symfony Process dengan argument terpisah/escaping aman.
- [ ] Jangan membangun command dari input user.
- [ ] Jangan mengekspos password di log, metadata, atau process list.

### Archive, manifest, dan checksum

- [ ] Buat struktur archive database, files/private, files/public, manifest, dan checksums.
- [ ] Manifest memuat UUID, versi aplikasi, waktu/zona, driver, jumlah/ukuran file, include/exclude, dan checksum komponen.
- [ ] Hitung checksum komponen utama.
- [ ] Tulis archive ke disk final.
- [ ] Baca ulang file final dan verifikasi ukuran/checksum.
- [ ] Jangan menandai completed sebelum verifikasi file final berhasil.
- [ ] Simpan checksum SHA-256 final di metadata.

### Lock dan failure handling

- [ ] Gunakan global atomic lock `backup:platform`.
- [ ] Tolak request kedua saat backup berjalan.
- [ ] Buat temporary write lock yang dibaca middleware aplikasi.
- [ ] Blokir semua mutasi tenant selama snapshot.
- [ ] Blokir mutasi platform yang mengubah database/file selama snapshot; izinkan hanya monitoring read-only yang aman.
- [ ] Tetap izinkan read-only access, health check, queue worker, dan system monitoring.
- [ ] Selalu lepas global lock dan write lock dalam `finally`.
- [ ] Bersihkan temporary artifact saat sukses maupun gagal.
- [ ] Ubah status failed dan simpan error ringkas yang sudah disanitasi.
- [ ] Jangan meninggalkan backup run selamanya berstatus running setelah crash; sediakan stale-run recovery yang terkontrol.

### Trigger manual dan scheduler

- [ ] Request HTTP manual hanya membuat metadata pending dan dispatch job.
- [ ] Minta konfirmasi sebelum trigger.
- [ ] Jangan menerima path, command, filename, atau folder dari browser.
- [ ] Download hanya melalui controller terautorisasi atau temporary private URL.
- [ ] Super admin dan tenant tidak dapat melihat, memicu, mengunduh, atau menghapus backup.
- [ ] Jadwalkan backup bulanan default tanggal 1 pukul 02.00 Asia/Jakarta.
- [ ] Gunakan `withoutOverlapping` dan `onOneServer` bila cache/deployment mendukung.
- [ ] Tetap gunakan lock aplikasi; jangan hanya mengandalkan scheduler lock.
- [ ] Jadikan scheduled run idempotent per periode bulan.
- [ ] Opsi force hanya tersedia lewat CLI terkontrol.

### Retensi dan alert

- [ ] Simpan 12 scheduled backup sukses terbaru secara default.
- [ ] Simpan 10 manual backup sukses terbaru secara default.
- [ ] Jalankan prune hanya setelah backup baru sukses.
- [ ] Jangan hapus run yang running.
- [ ] Jangan hapus backup terakhir yang sukses.
- [ ] Jangan hapus backup yang checksum-nya belum diverifikasi.
- [ ] Jangan hapus backup yang sedang dipakai restore.
- [ ] Manual delete meminta konfirmasi, menghapus lewat disk API, mengubah status metadata, dan mencatat audit.
- [ ] Tampilkan alert bila tidak ada backup sukses lebih dari 35 hari.
- [ ] Tampilkan alert bila run terakhir gagal.
- [ ] Tampilkan alert bila scheduled backup bulan berjalan belum ada.
- [ ] Tampilkan alert bila disk tidak writable atau kapasitas mendekati batas.

### Restore dan runbook

- [ ] Sediakan restore hanya melalui CLI, bukan tombol web.
- [ ] Minta confirmation token eksplisit.
- [ ] Verifikasi status completed dan checksum sebelum ekstraksi.
- [ ] Buat pre-restore backup terlebih dahulu.
- [ ] Batalkan restore bila pre-restore gagal, kecuali prosedur darurat CLI yang terdokumentasi.
- [ ] Aktifkan maintenance mode.
- [ ] Restore file ke staging directory sebelum swap.
- [ ] Restore database dengan utilitas driver standar.
- [ ] Jangan overwrite archive sumber.
- [ ] Selalu keluar dari maintenance mode melalui recovery/finally.
- [ ] Catat mulai, selesai, dan gagal ke activity log.
- [ ] Buat runbook staging dan jadwalkan uji restore setiap 3–6 bulan.

### Activity backup

- [ ] Catat `backup_requested`.
- [ ] Catat `backup_started`.
- [ ] Catat `backup_completed`.
- [ ] Catat `backup_failed`.
- [ ] Catat `backup_downloaded`.
- [ ] Catat `backup_deleted`.
- [ ] Catat `backup_restore_started`.
- [ ] Catat `backup_restore_completed`.
- [ ] Catat `backup_restore_failed`.
- [ ] Gunakan `log_name = system_backup` dan `koperasi_id = null`.
- [ ] Jangan log credential, signed URL, temp path absolut, atau isi backup.

**Gate selesai fase 7**

- [ ] Hanya system owner dapat mengelola backup.
- [ ] Manual trigger tidak menjalankan dump di request HTTP.
- [ ] Backup sukses berisi database, allowlisted uploads, manifest, dan checksum valid.
- [ ] Failure selalu membersihkan temporary artifact dan lock.
- [ ] Scheduled backup idempotent dan retensi tidak menghapus last-known-good.
- [ ] Restore menolak checksum salah dan membatalkan bila pre-restore gagal.

## Fase 8 — Automated test, regression, dan rollout

### Test role/platform

- [ ] System owner mempunyai `koperasi_id = null`.
- [ ] Super admin mempunyai `koperasi_id = null`.
- [ ] Tenant role wajib mempunyai `koperasi_id`.
- [ ] Hanya system owner memperoleh Gate bypass.
- [ ] Super admin tidak dapat CRUD system owner.
- [ ] Admin primer tidak dapat memberikan role platform.
- [ ] Role protected tidak dapat diedit/dihapus melalui single atau bulk action.
- [ ] Last system owner tidak dapat dihapus/didemote.
- [ ] Last super admin tidak dapat dihapus/didemote.
- [ ] Last admin primer tenant tidak dapat dihapus/didemote tanpa pengganti.
- [ ] System owner dapat membuka `/system/*`.
- [ ] Super admin mendapat 403/404 pada `/system/*`.
- [ ] System owner dan super admin dapat membuka `/platform/*`.
- [ ] Tenant user mendapat 403/404 pada `/platform/*`.
- [ ] Platform user diarahkan ke dashboard yang benar.
- [ ] Permission baru tidak otomatis menjadi milik super admin.
- [ ] Gate bypass tidak melewati flow audit support/impersonation.
- [ ] Tenant user tidak dapat memindahkan profile ke unit tenant lain.

### Test tenant isolation

- [ ] Tenant A hanya melihat Unit Kerja A.
- [ ] Tenant A hanya melihat User A.
- [ ] Tenant A hanya melihat Karyawan A.
- [ ] Tenant A hanya melihat Barang A.
- [ ] Tenant A hanya melihat Hari Libur A.
- [ ] Tenant A hanya melihat Komponen Gaji A.
- [ ] Tenant A hanya melihat Transaksi Gaji A.
- [ ] Tenant A hanya melihat Absensi A.
- [ ] Tenant A tidak dapat direct view/edit/delete data B.
- [ ] Tenant A tidak dapat memilih foreign ID milik B.
- [ ] Tenant A tidak dapat menetapkan atasan dari B.
- [ ] Tenant A tidak dapat memakai komponen gaji B.
- [ ] Tenant A tidak dapat bulk delete campuran A/B.
- [ ] Tenant A tidak dapat mencetak barcode/QR B.
- [ ] Tenant A tidak dapat mengunduh dokumen B.
- [ ] Nested foto/dokumen parent tenant lain menghasilkan 404.
- [ ] Tenant A tidak dapat melihat/cetak/export laporan B.
- [ ] Tenant A tidak dapat mengubah hari operasional/libur B.
- [ ] Penyusutan A tidak memasukkan barang B.
- [ ] Kode barang dan NIK yang sama boleh digunakan A dan B.
- [ ] Nilai unique yang sama dalam satu tenant tetap ditolak.
- [ ] Parent change menghitung ulang ownership dengan benar.
- [ ] Invariant checker mendeteksi mismatch buatan.

### Test subscription

- [ ] Payment pending tidak mengaktifkan subscription.
- [ ] Payment rejected tidak mengaktifkan subscription.
- [ ] Payment verified mengaktifkan/memperpanjang subscription.
- [ ] Verifikasi bersifat atomik dan idempotent.
- [ ] Perpanjangan awal tidak membuang sisa periode.
- [ ] Expired tenant tidak dapat write.
- [ ] Suspended tenant mengikuti aturan read-only yang diputuskan.
- [ ] Platform actor tetap dapat mengakses expired tenant sesuai permission.
- [ ] Scheduler mengubah status berdasarkan tanggal.
- [ ] Invoice pending menjadi overdue.
- [ ] Onboarding rollback bila koperasi, subscription, atau admin gagal dibuat.
- [ ] Tenant expired tidak mempunyai form payment.
- [ ] Status/profile/logout/export tetap tersedia sesuai kebijakan.

### Test activity/impersonation

- [ ] Aktivitas tenant A mempunyai `koperasi_id = A`.
- [ ] Tenant A tidak dapat melihat activity B.
- [ ] Aksi owner pada A mencatat owner sebagai causer dan A sebagai target.
- [ ] Aksi super admin pada B mencatat super admin sebagai causer dan B sebagai target.
- [ ] Verifikasi payment mencatat invoice, nominal, actor, dan waktu.
- [ ] Perubahan role tercatat.
- [ ] Bulk action tercatat.
- [ ] Download dokumen sensitif tercatat.
- [ ] Impersonation mulai/selesai tercatat dengan alasan.
- [ ] Nested impersonation ditolak.
- [ ] Aksi selama impersonation tetap mencatat owner asli.
- [ ] Password/token/credential tidak tersimpan di properties.
- [ ] Log platform non-tenant boleh `koperasi_id = null`.
- [ ] Cleanup retensi tidak menghapus payment/subscription terlalu cepat.

### Test cache/storage

- [ ] Cache dashboard A tidak dipakai B.
- [ ] Perubahan A hanya invalidate A dan agregasi platform.
- [ ] File baru tersimpan di folder tenant benar.
- [ ] Migrasi file existing memperbarui path dengan aman.
- [ ] Download selalu melewati policy.
- [ ] File rollback dibersihkan.
- [ ] Hari Libur dan Pengaturan A tidak memengaruhi perhitungan B.

### Test backup

- [ ] Hanya system owner dapat membuka `/system/backups`.
- [ ] Super admin dan tenant ditolak pada seluruh route backup.
- [ ] Trigger manual membuat pending run dan dispatch job.
- [ ] Scheduled run maksimal sekali per periode bulan.
- [ ] Global lock mencegah dua backup paralel.
- [ ] Write lock menolak mutasi selama snapshot.
- [ ] Archive menyertakan dump, allowlisted uploads, manifest, dan checksums.
- [ ] Exclude list benar-benar tidak masuk archive.
- [ ] Status completed baru terjadi setelah final checksum verified.
- [ ] Failure mengubah status, membersihkan temp, dan melepas lock.
- [ ] Scheduled actor null.
- [ ] Manual actor tercatat.
- [ ] Download tidak memakai public URL.
- [ ] Retensi melindungi backup terakhir yang sukses.
- [ ] Manual delete memperbarui metadata dan audit.
- [ ] Alert muncul setelah 35 hari tanpa backup sukses.
- [ ] Restore menolak checksum mismatch.
- [ ] Restore batal bila pre-restore gagal.
- [ ] Maintenance mode selalu dipulihkan setelah restore gagal.

### Regression dan quality gate

- [ ] Refactor helper/fixture test single-tenant tanpa mengurangi assertion existing.
- [ ] Jalankan seluruh unit test.
- [ ] Jalankan seluruh feature test.
- [ ] Jalankan suite SQLite.
- [ ] Jalankan suite MySQL/MariaDB.
- [ ] Jalankan formatter dan static checks proyek.
- [ ] Uji login dan landing ketiga jenis user secara browser.
- [ ] Uji UI responsive untuk dashboard system/platform dan status subscription.
- [ ] Uji direct URL, request manual, bulk action, download, export, dan nested route.
- [ ] Uji queue worker dan scheduler pada environment staging.
- [ ] Uji backup manual dan scheduled pada staging.
- [ ] Lakukan restore drill staging dari archive hasil aplikasi.

### Rollout production

- [ ] Deploy migration additive terlebih dahulu.
- [ ] Jalankan preflight data/invariant report.
- [ ] Ambil backup pemulihan sebelum bootstrap.
- [ ] Jalankan bootstrap dengan parameter eksplisit.
- [ ] Verifikasi hasil dan jumlah row per tenant.
- [ ] Baru aktifkan constraint non-null/composite.
- [ ] Baru aktifkan route/middleware tenant dan subscription.
- [ ] Pastikan queue worker dan scheduler menggunakan release terbaru.
- [ ] Pastikan backup disk off-site writable.
- [ ] Pantau error, failed jobs, slow query, storage, dan activity security setelah rollout.
- [ ] Siapkan rollback aplikasi dan recovery data yang tidak bergantung pada migration destructive.

## Matriks ownership modul

| Modul/model | Ownership | Sumber `koperasi_id` saat create/update | Catatan |
|---|---|---|---|
| User tenant | Root | Tenant actor atau tenant tujuan tervalidasi | Unit wajib dari tenant yang sama |
| Unit Kerja | Root | Tenant actor/tujuan | Nama dan kode unique per tenant |
| Karyawan | Root + parent | Diturunkan dari Unit Kerja | Atasan harus satu tenant |
| Barang | Root + parent | Diturunkan dari Unit Kerja | Kode generator dan storage tenant-aware |
| Absensi | Root + parent | Diturunkan dari Karyawan | Unique tetap per karyawan/tanggal |
| Komponen Gaji | Root | Tenant actor/tujuan | Detail transaksi harus memakai komponen satu tenant |
| Transaksi Gaji | Root + parent | Diturunkan dari Karyawan | Detail mengikuti transaksi |
| Pengaturan | Root | Tenant actor/tujuan | Jangan campur dengan setting platform |
| Hari Libur | Root | Tenant actor/tujuan | Sinkronisasi API dilakukan per tenant |
| Riwayat Kondisi Barang | Child | Mengikuti Barang | Resolve melalui relasi parent |
| Foto Barang | Child | Mengikuti Barang | Path storage diberi prefix tenant |
| Dokumen Barang | Child | Mengikuti Barang | Private dan download ber-policy |
| Dokumen Karyawan | Child | Mengikuti Karyawan | Private dan download ber-policy |
| Transaksi Gaji Detail | Child | Mengikuti Transaksi Gaji | Komponen sumber harus tenant yang sama |
| Koperasi | Platform | Tidak berlaku | Dikelola system owner/super admin |
| Package | Platform global | Tidak berlaku | Tidak memakai `koperasi_id` |
| Subscription | Platform → tenant | Koperasi terpilih | Snapshot harga/durasi wajib |
| Invoice | Platform → tenant | Koperasi/subscription | Nomor invoice unique global |
| Payment | Child invoice | Mengikuti Invoice | Bukti private |
| Activity | Tenant target nullable | Tenant subject/data yang terkena | Bukan tenant causer |
| BackupRun | Platform global | `koperasi_id = null` | Archive berisi seluruh tenant |

## Definition of Done akhir

- [ ] Kelima role target ada dengan invariant tenant/platform yang benar.
- [ ] Hanya system owner memperoleh Gate bypass.
- [ ] Protected role dan akun terakhir terlindungi.
- [ ] Route `/system`, `/platform`, dan tenant terpisah.
- [ ] Seluruh query tenant-owned, raw query, join, subquery, filter, bulk action, nested route, cetak, export, dan download ter-scope.
- [ ] Seluruh parent-child invariant dijaga pada create/update dan diverifikasi setelah migrasi.
- [ ] Hari Libur dan Penyusutan ikut tenant-aware.
- [ ] Storage, cache, Pengaturan, Hari Operasional, dan generator kode tenant-aware.
- [ ] Subscription manual, invoice, payment, scheduler, dan onboarding atomik berfungsi.
- [ ] Tenant expired/suspended mengikuti kebijakan akses tanpa payment self-service.
- [ ] Activitylog tenant-aware mencatat event penting tanpa secret.
- [ ] Impersonation selalu beralasan dan mempertahankan causer asli.
- [ ] Backup manual/bulanan, checksum, manifest, lock, retensi, alert, dan audit berfungsi.
- [ ] Restore CLI memverifikasi checksum dan membuat pre-restore backup.
- [ ] Backup production tersimpan off-site/berbeda media.
- [ ] Seluruh test baru dan regression test existing lulus pada SQLite dan database production-compatible.
- [ ] Restore drill staging berhasil dan runbook diperbarui.

## Di luar scope

- Payment gateway.
- Self-service pembayaran tenant.
- Company profile/website publik.
- Database atau subdomain terpisah per tenant.
- Spatie teams.
- Rewrite Blade ke frontend SPA.
- Tombol restore backup melalui web.
- Enkripsi archive custom.
- Retrofit UI lama yang tidak disentuh oleh fitur ini.
- Implementasi 2FA baru; namun akun system owner wajib memakai 2FA ketika mekanismenya tersedia.
