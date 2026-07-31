# Rencana Multi-Tenancy Ringan untuk SaaS Koperasi

Status: **Draft disetujui secara konsep, belum mulai implementasi.**
Terakhir diperbarui: 2026-07-30.

## 1. Latar Belakang & Tujuan

Aplikasi ini saat ini single-tenant (satu database, satu organisasi). Rencananya
aplikasi akan ditawarkan sebagai **produk berlangganan** ke banyak koperasi
(pelanggan), dijalankan dari **satu instance/deploy yang sama** — bukan
instalasi terpisah per pelanggan.

Kita **tidak** membangun sistem billing/subscription. Cukup:

- Super Admin (vendor aplikasi) membuat akun `admin_primer` untuk tiap
  koperasi pelanggan, dengan batas waktu (`expires_at`) yang diatur manual.
- Kalau lewat batas waktu, akun-akun di koperasi itu diblokir login sampai
  diperpanjang oleh Super Admin.

### Kesalahan konsep yang perlu dihindari

Menambahkan role baru (mis. "Primer") **saja** tidak cukup untuk memisahkan
data antar pelanggan. Role/permission (Spatie) hanya mengatur *aksi apa yang
boleh dilakukan*, bukan *baris data mana yang boleh dilihat*. Tanpa kolom
penanda tenant di skema data, seorang `admin_primer` di Koperasi A akan tetap
melihat data `karyawan`, `barang`, `absensi`, dst milik Koperasi B — itu
kebocoran data lintas pelanggan, bukan cuma soal tampilan.

Karena itu isolasi tenant **wajib diterapkan di level data** (kolom
`koperasi_id` + query scope), bukan hanya lewat permission/menu sidebar.

## 2. Keputusan Desain (hasil diskusi)

### 2.1 Model tenancy

- **Shared database, discriminator column** — satu tabel `koperasi` sebagai
  tenant, dan kolom `koperasi_id` di semua tabel domain milik tenant.
- Bukan multi-database / multi-schema per tenant (terlalu berat untuk
  kebutuhan "mini tenancy ringan").

### 2.2 Role platform (hanya 2 role sistem)

| Role | `koperasi_id` | Cakupan akses |
|---|---|---|
| `super_admin` | `null` (global) | Lintas semua koperasi. Kelola entitas `Koperasi` (buat/perpanjang/nonaktifkan, set `expires_at`). Satu-satunya yang boleh membuat/menghapus Role, dan satu-satunya yang boleh menetapkan role `super_admin`/`admin_primer` ke user manapun. |
| `admin_primer` | terikat 1 koperasi | Full permission (`PermissionCatalog`) **di koperasinya sendiri**, kecuali dua pengecualian di bawah. |

**Pengecualian akses `admin_primer` (wajib, bukan opsional):**

1. **Tidak bisa assign role `super_admin`/`admin_primer`** ke user manapun.
   Ini *hardcoded guard* di `UserService`, **bukan** checkbox permission biasa
   — supaya `admin_primer` tidak bisa membuat "Super Admin bayangan" atau
   admin_primer tambahan sendiri.
2. **Hanya dapat `role.view` + `role.update`**, tidak pernah `role.create`
   atau `role.delete`. Artinya `admin_primer` hanya bisa mengubah permission
   pada role yang sudah ada di koperasinya, tidak bisa membuat role baru dari
   nol.

Role `Staff` yang ada sekarang **tidak otomatis dibuatkan** untuk tiap
koperasi baru. Saat Super Admin membuat koperasi baru, sistem hanya
men-seed role `admin_primer` untuk tenant itu dan langsung memasangnya ke
akun pertama. Kalau koperasi butuh role tambahan untuk karyawannya (mis. staf
gudang, kasir), **Super Admin yang membuatkan role tersebut secara manual**
untuk koperasi itu — `admin_primer` baru bisa mengatur ulang permission-nya,
tidak membuatnya sendiri.

### 2.3 Isolasi data

- Semua tabel domain tenant butuh kolom `koperasi_id` + global scope Eloquent
  yang otomatis filter berdasarkan `koperasi_id` user yang login.
- `super_admin` bypass scope ini sepenuhnya (lihat semua koperasi).
- Satu `admin_primer` **tidak pernah** boleh melihat data koperasi lain —
  ini persyaratan keras, harus diuji lewat automated test, bukan cuma
  diasumsikan lewat UI hiding.

### 2.4 Masa aktif (expiry)

- `expires_at` disimpan **per koperasi** (bukan per user) — satu tanggal
  berlaku untuk seluruh akun (`admin_primer` + role lain) di koperasi itu.
- Kalau lewat `expires_at` → **blokir total login** untuk semua user di
  koperasi tsb (bukan mode read-only). Berlaku sampai Super Admin
  memperpanjang tanggalnya.
- Tidak ada sistem billing/invoice — perpanjangan murni tindakan manual
  Super Admin lewat modul Manajemen Koperasi.

## 3. Desain Skema Data

### Tabel baru: `koperasi`

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | bigint PK | |
| `nama` | string | Nama koperasi pelanggan |
| `expires_at` | timestamp nullable | Batas masa aktif langganan |
| `is_active` | boolean default true | Nonaktif manual oleh Super Admin (di luar soal expiry) |
| timestamps | | |

### Kolom `koperasi_id` (nullable FK ke `koperasi`) ditambahkan ke

- `users` (`null` khusus untuk `super_admin`)
- `unit_kerja`
- `karyawan`
- `barang`
- `absensi`
- `hari_libur`
- `komponen_gaji`
- `transaksi_gaji`
- `pengaturan`
- `roles` (tabel Spatie) — role yang dibuat untuk satu koperasi tidak boleh
  terlihat/kepakai di koperasi lain. Role global (`super_admin`) punya
  `koperasi_id = null`.

> Catatan implementasi: cek ulang tabel turunan lain yang saat ini
> mengasumsikan single-tenant (mis. tabel riwayat/dokumen yang relasinya
> lewat `karyawan`/`barang`) — kemungkinan besar cukup ikut ter-scope lewat
> relasi induknya, tapi perlu diverifikasi satu per satu saat implementasi,
> jangan diasumsikan otomatis aman.

## 4. Perubahan Kode yang Diperkirakan

Mengikuti pola layered yang sudah dipakai di proyek ini (Request →
Repository → Service → Controller, komponen Blade reusable):

- **Trait/global scope** `BelongsToKoperasi` — dipasang di semua model tenant,
  auto-filter `koperasi_id` dari user yang login, bypass untuk `super_admin`.
- **Auto-assign `koperasi_id`** saat create — dilakukan di layer Service
  (bukan dari input form), ambil dari `auth()->user()->koperasi_id`.
- **Middleware** `EnsureKoperasiActive` di grup route `auth` — cek
  `koperasi->expires_at` & `is_active`, redirect ke halaman "masa aktif
  berakhir" kalau tidak valid.
- **`UserService`** — tambah guard: tolak assign role `super_admin`/
  `admin_primer` kecuali aktor yang melakukan adalah `super_admin`.
- **`RoleService`/`RoleController`** — `role.create` & `role.delete` cuma
  bisa dieksekusi oleh `super_admin` (baik lewat permission maupun guard
  eksplisit); scoping query Role ikut `koperasi_id` (dengan pengecualian
  `super_admin` yang butuh selector koperasi di UI-nya).
- **`PermissionCatalog`/`PermissionSeeder`** — sesuaikan agar men-seed role
  `super_admin` (global) & `admin_primer` (template per koperasi saat
  provisioning), bukan lagi `Admin`/`Staff` seperti sekarang.
- **`NavigationMenu`** — tambah grup menu baru "Manajemen Koperasi" (khusus
  `super_admin`), dan pastikan tombol "Tambah Role"/hapus Role disembunyikan
  untuk `admin_primer` di halaman Role & Hak Akses.
- **Modul baru "Koperasi"** (CRUD, khusus Super Admin): Model, Migration,
  Repository, Service, Controller, Form Requests, Views — mengikuti pola
  modul lain yang sudah ada (lihat `RoleController`/`RoleService`/
  `RoleRepository` sebagai referensi pola). Saat store: buat row `koperasi`
  + buat user pertama dengan role `admin_primer` + seed role `admin_primer`
  untuk tenant itu dalam satu transaction.

## 5. Asumsi Terbuka (sudah dikonfirmasi)

- [x] **Data existing**: belum ada data produksi nyata. Tidak perlu migration
      backfill data lama — cukup tambah kolom `koperasi_id` nullable, dan data
      contoh/dummy untuk pengembangan dibuat lewat **seeder** (bukan migrasi
      data produksi).
- [x] Nama tampilan role di UI: **"Admin Primer"** (bukan mentah
      `admin_primer`) untuk keterbacaan, sementara nama role/permission di
      kode tetap snake_case `admin_primer`.

## 6. Checklist Pengerjaan

### Fase 0 — Persiapan
- [x] Konfirmasi asumsi terbuka di atas (bagian 5) dengan pemilik produk
- [ ] Backup database sebelum menjalankan migration skema besar

### Fase 1 — Skema & Migration
- [x] Migration: buat tabel `koperasi` (`nama`, `expires_at`, `is_active`)
- [x] Migration: tambah `koperasi_id` (nullable FK) ke `users`
- [x] Migration: tambah `koperasi_id` (nullable FK) ke `unit_kerja`,
      `karyawan`, `barang`, `absensi`, `hari_libur`, `komponen_gaji`,
      `transaksi_gaji`, `pengaturan`
- [x] Migration: tambah `koperasi_id` (nullable FK) ke tabel `roles` (Spatie)
- [ ] Seeder: buat 1-2 `koperasi` contoh + akun `admin_primer` masing-masing
      untuk kebutuhan development/testing (tidak ada data produksi lama yang
      perlu di-backfill) — **dipindah ke Fase 8**, butuh Model `Koperasi` &
      role `admin_primer` dari Fase 2/4 dulu.

> **Catatan implementasi (temuan saat migrasi):**
> - `unit_kerja.nama_unit`, `karyawan.nik`, `barang.kode_barang`,
>   `hari_libur.tanggal`, dan `roles(name, guard_name)` diubah jadi unique
>   **composite** bareng `koperasi_id` — aman karena kode yang menulis ke
>   tabel-tabel ini (`firstOrCreate`, `Role::findOrCreate`, Form Request
>   validation) selalu melakukan pengecekan eksistensi dulu, bukan blind
>   insert, jadi tidak terpengaruh NULL-vs-NULL di unique index.
> - `pengaturan.key` **sengaja TIDAK** diubah jadi composite unique di fase
>   ini. `KodeBarangGenerator::pengaturanTerkunci()` memakai
>   `Pengaturan::insertOrIgnore()` sebagai mutex yang mengandalkan `key`
>   unik sendirian — kalau constraint-nya diubah jadi composite sekarang
>   (sebelum kode aplikasi diperbarui untuk selalu mengisi `koperasi_id`
>   asli), baris-baris dengan `koperasi_id NULL` tidak akan saling mencegah
>   duplikat (NULL tidak dianggap sama dengan NULL di unique index MySQL/
>   SQLite) — mutex-nya jadi bocor dan menghasilkan data salah (kejadian
>   nyata, ketahuan lewat test `PengaturanTest` saat migrasi ini dikerjakan).
>   Kolom `koperasi_id` tetap ditambahkan (nullable), tapi perubahan
>   constraint ke composite ditunda sampai `Pengaturan` benar-benar
>   di-scope per koperasi (Fase 2/6), supaya perubahan skema & perubahan
>   kode yang menjaganya berjalan bersamaan.

### Fase 2 — Model & Infrastruktur Tenant Scope
- [x] Model `Koperasi` (`app/Models/Koperasi.php`) + relasi `hasMany` ke
      `User` dan semua tabel domain terkait
- [x] Trait `BelongsToKoperasi` (`app/Models/Concerns/BelongsToKoperasi.php`)
      — pasang `KoperasiScope` (`app/Models/Scopes/KoperasiScope.php`) +
      auto-isi `koperasi_id` dari user yang login saat record baru dibuat
      (hanya kalau belum diisi eksplisit — supaya alur super_admin bikin
      data untuk koperasi lain tetap bisa override manual)
- [x] Pasang trait ke `UnitKerja`, `Karyawan`, `Barang`, `Absensi`,
      `HariLibur`, `KomponenGaji`, `TransaksiGaji`, dan `User`
- [x] Update `User` model: relasi `koperasi()` (lewat trait) + helper
      `isSuperAdmin()` / `isAdminPrimer()` (cek `hasRole()`, role-nya baru
      benar-benar ada setelah Fase 4)
- [ ] **Ditunda ke Fase 4**: trait belum dipasang ke `roles` (Spatie) —
      butuh custom model `App\Models\Role extends Spatie\Permission\Models\Role`
      + ubah `config('permission.models.role')`. Ini sengaja dikerjakan
      bareng guard `role.create`/`role.delete` di Fase 4, bukan di sini,
      supaya perubahan resolusi model Spatie (yang berdampak luas) dan
      guard-nya diuji sebagai satu unit yang koheren.
- [ ] **Ditunda ke Fase 6**: trait belum dipasang ke `Pengaturan` — alasan
      sama seperti catatan di Fase 1 (mutex `insertOrIgnore` di
      `KodeBarangGenerator` akan bocor kalau di-scope sebelum kode
      `get()`/`set()`/`pengaturanTerkunci()` diperbarui untuk selalu
      pakai `koperasi_id` asli).

Sudah diverifikasi: full test suite tetap 302/303 (kegagalan yang tersisa
sama persis dengan yang sudah dikonfirmasi pra-eksisting di Fase 1, termasuk
`AuthenticationTest`/`AuthorizationTest` yang lolos penuh — global scope
tidak mengganggu proses login karena `auth()->user()` belum resolve saat
guard mengambil user dari DB).

### Fase 3 — Auth & Middleware
- [x] Middleware `EnsureKoperasiActive` (`app/Http/Middleware/EnsureKoperasiActive.php`,
      alias `koperasi.active`) — cek `expires_at` + `is_active`, lolos kalau
      user tidak punya koperasi (`koperasi_id` null, mis. `super_admin`)
- [x] Daftarkan middleware — dipisah jadi 2 grup di `routes/web.php`:
      `Route::middleware('auth')` (cuma untuk halaman
      `koperasi.expired` itu sendiri, supaya tidak infinite-redirect) dan
      `Route::middleware(['auth', 'koperasi.active'])` untuk seluruh route
      aplikasi lainnya. Route `logout` tidak kena middleware ini sama
      sekali (didaftarkan lewat `Auth::routes()` + `LoginController`,
      di luar kedua grup di atas), jadi user yang terblokir tetap bisa
      logout.
- [x] Halaman `koperasi.expired` (`KoperasiExpiredController` +
      `resources/views/koperasi/expired.blade.php`, pakai `layouts.auth`
      minimal tanpa sidebar) — auto-redirect balik ke dashboard kalau
      user nyasar ke sini padahal koperasinya masih aktif
- [x] Test (`tests/Feature/EnsureKoperasiActiveTest.php`, 6 test): expired
      → diblokir, nonaktif → diblokir, aktif → tidak diblokir, tanpa
      koperasi → tidak diblokir, user terblokir tetap bisa lihat halaman
      expired & logout, user tidak terblokir nyasar ke halaman expired
      → dialihkan ke dashboard

### Fase 4 — Role & Permission
- [x] Custom model `App\Models\Role extends Spatie\Permission\Models\Role`
      (pasang `BelongsToKoperasi`) + `config('permission.models.role')`
      diarahkan ke situ. Import `Spatie\Permission\Models\Role` diganti ke
      `App\Models\Role` di `RoleController`, `RoleService`,
      `RoleRepository`, `UserController` (satu unit koheren, sesuai catatan
      penundaan di Fase 2).
- [x] `PermissionSeeder`: role `Admin` diganti jadi `super_admin` (global,
      semua permission via `PermissionCatalog::all()`). Seed role `Staff`
      **dihapus** dari seeder global.
- [x] `Staff` sekarang cuma contoh role demo/dev — dipindah ke seeder baru
      `database/seeders/DemoStaffRoleSeeder.php` (dipanggil dari
      `DatabaseSeeder` sebelum `UserSeeder`, dan dari helper test
      `staffUser()`). Role per-koperasi asli (`admin_primer` + role
      tambahan) baru benar-benar di-provisioning di **Fase 5**.
- [x] **Guard ganda** (permission checkbox TETAP ada di catalog + hardcoded
      check, sesuai rencana awal "baik lewat permission maupun guard
      eksplisit"):
      - `StoreRoleRequest::authorize()`: `can('role.create') && isSuperAdmin()`
      - `RoleController::create()`: tambahan `abort_unless(isSuperAdmin())`
      - `RoleService::store()/destroy()/destroyMany()`: terima parameter
        `User $actor`, lempar `DomainException` kalau bukan super_admin —
        lapisan pertahanan kedua di level Service, independen dari HTTP/
        FormRequest (menutup celah kalau ada role lain yang suatu saat
        salah dikonfigurasi punya permission `role.create`/`role.delete`).
- [x] `UserService::store()/update()` sekarang terima parameter
      `User $actor` — lempar `DomainException` kalau aktor mencoba
      menetapkan role `super_admin`/`admin_primer` tanpa jadi super_admin
      sendiri (hardcoded, independen dari permission `pengguna.update`).
- [x] `UserController::selectableRoles()` — dropdown pemilihan role di form
      pengguna otomatis menyembunyikan `super_admin`/`admin_primer` untuk
      aktor yang bukan super_admin (UX; guard sesungguhnya tetap di Service).
- [x] `resources/views/role/index.blade.php`: tombol "Tambah Role" & aksi
      hapus role sekarang dikunci ke `@if(auth()->user()->isSuperAdmin())`,
      bukan `@can('role.create'/'role.delete')` — konsisten dengan guard
      hardcoded di atas.
- [x] Test baru (`tests/Feature/SuperAdminGuardTest.php`, 4 test): non-super
      admin gagal `role.create` (403) & `role.delete` (diblokir di Service
      walau permission-nya sengaja diberikan), gagal assign role
      `super_admin` ke user lain, dan super_admin tetap bisa create/delete
      role normal.

> **Catatan implementasi (temuan saat migrasi):** migrasi lama
> `2026_07_30_000005_add_employee_history_permissions.php` (dari sebelum
> proyek multi-tenant ini dimulai) memanggil `Role::findOrCreate('Admin', 'web')`
> langsung di dalam migration. Karena migration jalan sebelum seeder,
> ini diam-diam membuat role `Admin` yang jadi yatim piatu (tidak pernah
> dipakai) begitu `PermissionSeeder` berganti nama jadi `super_admin` —
> ketahuan lewat `DatabaseSeederTest` yang tiba-tiba menghitung 3 role,
> bukan 2. Migration itu diperbaiki (`'Admin'` → `'super_admin'`) supaya
> instalasi baru tidak pernah membuat identitas basi ini.

### Fase 5 — Modul "Manajemen Koperasi" (khusus Super Admin)
- [x] Migration sudah selesai di Fase 1
- [x] `Koperasi` Model (Fase 2), `KoperasiRepository`, `KoperasiService`
      (`app/Repositories/KoperasiRepository.php`,
      `app/Services/KoperasiService.php`)
- [x] `KoperasiController` + `StoreKoperasiRequest`/`UpdateKoperasiRequest`
- [x] Views: `koperasi/index.blade.php` (search sederhana, badge status
      Aktif/Nonaktif/Lewat Masa Aktif), `koperasi/form.blade.php` (satu
      form dipakai create & edit — section "Akun Admin Primer Pertama"
      cuma muncul saat create, checkbox `is_active` cuma muncul saat edit)
- [x] Alur store: buat `koperasi` + role `admin_primer` (koperasi_id diisi
      manual, BUKAN lewat auto-assign trait — aktor super_admin sendiri
      tidak terikat koperasi manapun) + user pertama, satu `DB::transaction`.
      `PermissionCatalog::adminPrimerTemplate()` (baru) = semua permission
      KECUALI `role.create`/`role.delete` — konsisten dengan guard hardcode
      di Fase 4, supaya checkbox role admin_primer sendiri tidak
      menampilkan izin yang toh tidak pernah bisa dipakai.
- [x] Tambah entri menu "Manajemen Koperasi" di `NavigationMenu.php` — pakai
      key baru `'super_admin_only' => true` di skema item (bukan
      `'permission'`, karena kelola koperasi tidak masuk akal jadi
      permission checkbox untuk role manapun), ditaruh di grup
      "Administrasi" yang sudah ada.
- [x] Middleware baru `EnsureIsSuperAdmin` (alias `super_admin`), dipasang
      di grup route `koperasi.*` (`routes/web.php`) — index/create/store/
      edit/update saja, sengaja **tidak ada destroy**: koperasi punya FK
      `restrictOnDelete` ke hampir semua tabel tenant (users, roles, dst),
      jadi begitu koperasi provisioning selesai (selalu langsung py ≥1 user
      + role), hard-delete jadi mustahil secara alami — nonaktifkan lewat
      `is_active`/`expires_at`, bukan hapus baris.
- [x] Test baru (`tests/Feature/KoperasiTest.php`, 6 test): akses ditolak
      utk non-super_admin, index bisa diakses super_admin, provisioning
      membuat koperasi+role+user dengan benar (termasuk permission
      admin_primer tidak memuat role.create/delete), **dua koperasi
      masing-masing dapat role "admin_primer" sendiri tanpa tabrakan nama**
      (diuji eksplisit — lihat catatan di bawah), **isolasi data nyata
      antar-tenant** (admin_primer koperasi A tidak bisa lihat data
      koperasi B — baru bisa diuji sungguhan sekarang karena akhirnya ada
      >1 koperasi asli), dan update status/masa aktif koperasi.

> **Catatan implementasi (temuan penting saat membangun modul ini):**
> Beberapa koperasi akan punya role dengan nama **sama persis**
> (`admin_primer` di tiap tenant). Spatie's `$user->assignRole('admin_primer')`
> / `syncRoles([...])` yang dipanggil dengan **string nama** me-resolve ke
> `Role::findByName()` — yang otomatis ikut ter-scope oleh
> `KoperasiScope` HANYA kalau aktor yang login BUKAN super_admin (super_admin
> bypass scope, jadi lihat SEMUA role `admin_primer` lintas tenant tanpa
> filter). Karena `KoperasiService::store()` dijalankan oleh super_admin,
> assign-by-name di situ akan ambigu (bisa kena role admin_primer koperasi
> lain). Fix: `KoperasiService` selalu assign lewat **instance Role** yang
> baru dibuat (`$user->assignRole($role)`), bukan string nama — instance
> langsung dipakai by ID, tidak lewat lookup nama sama sekali. Alur
> `UserController`/`UserService` yang sudah ada (assign role lewat string
> dari dropdown form) tetap aman untuk sekarang karena selalu dijalankan
> oleh aktor ber-koperasi (ter-scope otomatis) — tapi kalau nanti
> super_admin butuh kelola user lintas-tenant lewat form yang sama, ini
> perlu direvisit (dicatat sebagai potensi pekerjaan Fase 6/7).

### Fase 6 — Scoping Modul Domain yang Sudah Ada
- [x] Auto-assign `koperasi_id` saat create — **tidak perlu sentuh tiap
      Service satu-satu**, sudah otomatis lewat trait `BelongsToKoperasi`
      (dipasang Fase 2) yang hook ke event `creating` model, berlaku untuk
      SEMUA jalur create (termasuk `firstOrCreate`, import Excel, dst),
      bukan cuma lewat Service.
- [x] `Pengaturan` & `TemplateSlipGaji` dituntaskan (keduanya sempat
      tertunda/terlewat dari Fase 1):
  - Migration baru `2026_07_31_000001_finish_koperasi_scoping_pengaturan_and_slip_gaji`
    — ubah `pengaturan.key` jadi composite unique `(koperasi_id, key)`,
    tambah `koperasi_id` ke `template_slip_gaji` (tabel yang KELUPAAN di
    daftar Fase 1 — juga singleton per-tenant, sama seperti pengaturan).
  - Trait `BelongsToKoperasi` dipasang ke kedua model.
  - `KodeBarangGenerator::pengaturanTerkunci()` diperbaiki: `insertOrIgnore`
    sekarang eksplisit isi `koperasi_id` dari `auth()->user()->koperasi_id`
    — sebelumnya kosong, itu yang bikin mutex-nya bocor di Fase 1.
  - **Batasan yang diketahui & diterima**: mutex ini masih bisa balik
    bocor kalau dipanggil TANPA user login sama sekali (bukan cuma
    null-tenant, tapi benar-benar tanpa aktor — konteks console/tanpa
    `actingAs()`), karena NULL tidak dianggap sama dengan NULL di unique
    index. Ini bukan skenario produksi nyata (barang selalu dibuat dalam
    request ter-otentikasi), jadi diterima sebagai batasan transisional,
    bukan diperbaiki paksa. Test terkait (`PengaturanTest`) diperbarui
    memakai aktor ber-koperasi sungguhan (`adminPrimerUser()`, helper baru
    di `tests/Pest.php`) supaya representatif dengan pemakaian nyata.
- [x] Audit Repository — **ditemukan 2 kebocoran nyata, keduanya
      diperbaiki**:
  1. **`DashboardRepository`** pakai `DB::table('barang')`/`DB::table('karyawan')`
     mentah di 4 tempat (`ringkasanInventaris`, `dataBelumLengkap`,
     `jumlahPerKondisiTerakhir`) — sama sekali tidak kena
     `KoperasiScope` (yang cuma berlaku untuk query Eloquent). Semua
     dashboard (total inventaris, nilai aset, data belum lengkap, kondisi
     inventaris) sebelumnya menghitung agregat LINTAS SEMUA KOPERASI.
     Diperbaiki dengan helper baru `App\Support\CurrentTenant::scopeQuery()`
     (versi "manual" dari `KoperasiScope`, untuk query yang tidak lewat
     Eloquent).
  2. **`DashboardCache`** — cache key dashboard (`dashboard:{versi}:{key}`)
     TIDAK dipisah per tenant sama sekali, cuma di-version globally. Kalau
     tidak diperbaiki, sekalipun query-nya sudah benar ter-scope, koperasi
     B yang memuat dashboard beberapa detik setelah koperasi A tetap akan
     melihat ANGKA CACHE MILIK KOPERASI A (TTL default 60 detik). Fix:
     cache key sekarang ikut segmen `koperasi-{id}` / `super-admin` / `anon`.
  - `LaporanRepository::rekapUnitKerjaPenggajian()` juga pakai
    `DB::table('transaksi_gaji')` mentah tapi **sudah aman** — dibatasi
    lewat subquery `whereIn('transaksi_gaji.id', $scopedBuilder->select('id'))`
    dari builder Eloquent yang sudah ter-scope, jadi ikut terfilter
    transitif. Diverifikasi lewat test, bukan cuma dinalar.
- [x] **Temuan kritis tak terduga**: memasang `BelongsToKoperasi` (global
      scope) ke model `Role` **merusak cache internal Spatie**.
      `PermissionRegistrar` meng-cache seluruh peta permission lewat
      `Permission::with('roles')` (eager load) — query ini otomatis ikut
      kena `KoperasiScope` juga, sehingga cache permission GLOBAL itu jadi
      bergantung pada `auth()->user()` siapapun yang kebetulan aktif saat
      cache pertama kali dibangun, dan mencemari hasil `$user->can(...)`
      untuk SEMUA user/tenant lain sampai cache invalidate. Ketahuan
      langsung lewat test yang gagal (permission check untuk tenant kedua
      balik `false`/null padahal seharusnya `true`).
  - **Fix**: `App\Models\Role` **tidak lagi pakai trait `BelongsToKoperasi`
    sama sekali** (tidak ada global scope, tidak ada auto-assign). Kolom
    `koperasi_id` tetap ada, diisi manual di tempat yang butuh
    (`KoperasiService::store()` saat provisioning).
  - Konsekuensi: tempat yang butuh "role milik koperasiku saja" harus
    filter eksplisit lewat `CurrentTenant::scopeQuery()` —
    `RoleRepository::paginate()`, `UserController::selectableRoles()`
    (dropdown pilih role di form pengguna) sudah diperbaiki.
  - Konsekuensi lain: `RoleController::edit()`/`update()` sekarang butuh
    guard manual (`abortIfOtherTenant()`, 404 kalau `$role->koperasi_id`
    bukan milik aktor) karena route model binding `{role}` tidak lagi
    otomatis ter-scope.
  - Konsekuensi lain lagi: `UserService::store()/update()` yang tadinya
    assign role lewat **string nama** (`$user->syncRoles([$data['role']])`)
    sekarang **resolve dulu jadi instance Role** yang di-scope manual ke
    koperasi aktor (`resolveAssignableRole()`), baru di-assign by instance
    — supaya nama role yang sama persis di banyak koperasi (mis. semua
    koperasi punya role "admin_primer") tidak pernah salah ke-assign lintas
    tenant. `KoperasiService::store()` sudah lebih dulu benar (sudah pakai
    instance, bukan nama) sejak Fase 5.
- [x] Audit tabel turunan (`RiwayatKondisiBarang`, `FotoBarang`,
      `DokumenBarang`, `DokumenKaryawan`, `RiwayatKaryawan` + turunannya,
      `TransaksiGajiDetail`) — **semua aman transitif**, sengaja TIDAK
      diberi kolom `koperasi_id` sendiri:
  - Selalu diakses lewat relasi dari parent yang sudah ter-scope
    (`$barang->fotoPendukung()->create(...)`, dst), tidak pernah query
    langsung ke tabel anak tanpa lewat parent.
  - Setiap controller yang menerima route parameter ganda (parent + anak,
    mis. `FotoBarangController::destroy(Barang $barang, FotoBarang $foto)`)
    SUDAH punya guard kepemilikan eksplisit
    (`abort_unless($foto->barang_id === $barang->id, 404)`) dari sebelum
    proyek ini — pola ini ternyata otomatis juga menutup celah "campur ID
    barang sendiri dengan ID foto milik tenant lain", diverifikasi lewat
    test baru.
- [x] Test baru (`tests/Feature/TenantScopingTest.php`, 5 test) + helper
      baru `adminPrimerUser()` di `tests/Pest.php` (bikin user ber-role
      admin_primer terikat 1 koperasi asli, dipakai test manapun yang
      butuh aktor tenant sungguhan — beda dari `adminUser()`/`staffUser()`
      yang koperasi_id-nya tetap null).

### Fase 7 — Navigasi & UI
- [x] `NavigationMenu.php` — sudah cukup lewat kerja Fase 5 (`super_admin_only`
      untuk item "Manajemen Koperasi") + sistem permission yang sudah ada:
      `admin_primer` otomatis cuma lihat menu sesuai permission role-nya
      (di-scope ke tenant sendiri), `super_admin` lihat semua menu bisnis
      (lintas semua koperasi, sesuai desain — mereka memang dimaksud full
      akses seperti "Admin" lama, bukan dibatasi) plus "Manajemen Koperasi".
      Tidak ada perubahan struktural lagi yang dibutuhkan di sini.
- [x] Review `@can(...)`/nama role hard-code — **bersih**, di-grep ulang di
      seluruh `app/` & `resources/views/`: satu-satunya referensi "Admin"/
      "Staff" yang tersisa memang sengaja (role contoh `DemoStaffRoleSeeder`
      + data demo `UserSeeder`, bukan sisa dari sistem role lama). Tombol
      "Tambah Role"/hapus role di `role/index.blade.php` sudah digerbang
      `@if(auth()->user()->isSuperAdmin())` sejak Fase 4, bukan permission
      checkbox — sudah benar, tidak perlu diubah.
- [x] **Temuan baru (di luar checklist awal, tapi bagian wajar dari "UI")**:
      role sistem (`super_admin`, `admin_primer`) tampil APA ADANYA
      (snake_case mentah) di 4 tempat UI — tabel Role & Hak Akses, dropdown
      pilih role di form Pengguna, badge role di daftar Pengguna, filter
      role di daftar Pengguna. Ini melanggar keputusan yang sudah disepakati
      di awal proyek ("Nama tampilan role di UI: **Admin Primer**, bukan
      mentah `admin_primer`"). Diperbaiki dengan `Role::displayName()`
      (method baru di `App\Models\Role`, map snake_case → label ramah,
      default ke nama asli untuk role custom tenant yang memang sudah
      manusiawi, mis. "Staff"). Dipakai di ke-4 tempat itu.
- [x] Test baru (`tests/Feature/RoleDisplayNameTest.php`, 2 test): role
      sistem tampil dengan label ramah ("Super Admin", "Admin Primer"),
      bukan snake_case mentah.

### Fase 8 — Testing
- [x] Feature test: `admin_primer` koperasi A tidak bisa melihat/mengakses
      data koperasi B — `tests/Feature/TenantIsolationTest.php` (5 test
      baru), mencakup modul yang belum diuji eksplisit di fase sebelumnya:
      `karyawan`, `absensi`, `hari_libur`, `komponen_gaji`, `transaksi_gaji`
      (sekaligus halaman detail `karyawan.show`/`transaksi-gaji.show` —
      404, bukan cuma hilang dari listing), `pengguna` (daftar user tidak
      bocor ke tenant lain), dan `role` (daftar role juga ter-scope, plus
      sekalian jadi regression test label ramah dari Fase 7 — halaman
      `role.index` tidak pernah menampilkan string mentah `admin_primer`).
      (`unit_kerja`, `barang`, `pengaturan` sudah diuji di Fase 5/6.)
- [x] Feature test: `super_admin` bisa melihat data lintas koperasi —
      test baru di file yang sama: `Karyawan::count()` dari sudut pandang
      `super_admin` menjumlahkan KEDUA koperasi, dan bisa buka halaman
      detail karyawan milik koperasi manapun.
- [x] Feature test: login diblokir saat koperasi expired — sudah selesai
      di Fase 3 (`tests/Feature/EnsureKoperasiActiveTest.php`, 6 test).
- [x] Feature test: `admin_primer` gagal saat mencoba `role.create`,
      `role.delete`, atau assign role `super_admin`/`admin_primer` — test
      baru: coba `role.store` (403, ditolak `StoreRoleRequest::authorize()`
      karena admin_primer memang tidak pernah punya permission
      `role.create`), coba hapus role `admin_primer` miliknya sendiri (403,
      guard `role.delete`), dan coba naikkan user lain jadi `super_admin`
      atau `admin_primer` lewat form Pengguna biasa (redirect + flash
      error dari `UserService::resolveAssignableRole()`, role tidak
      berubah — diverifikasi lewat assertion `hasRole()` tetap `false`).

**Sinkronisasi seeder demo** (di luar checklist Fase 8 tertulis, tapi
bagian wajar dari "testing merepresentasikan pemakaian nyata" — sekaligus
menuntaskan item Fase 1 yang sengaja ditunda: *"Seeder: buat 1-2 koperasi
contoh + akun admin_primer... dipindah ke Fase 8"*):
- `DatabaseSeeder` sekarang benar-benar memprovisioning satu **"Koperasi
  Demo"** lewat `KoperasiService::store()` yang sama persis dipakai
  Super Admin sungguhan di UI (bukan jalan pintas terpisah) — bikin role
  `admin_primer` + akun `admin.primer@example.com`.
- Setelah koperasi dibuat, `Auth::setUser()` di-set ke akun admin_primer
  tsb SEBELUM sisa seeder (unit kerja, karyawan, absensi, barang,
  komponen gaji, transaksi gaji, dst) dijalankan — jadi seeder-seeder itu
  **tidak perlu diubah sama sekali**, semua `koperasi_id`-nya ter-tag
  otomatis lewat trait `BelongsToKoperasi` yang sama dengan pemakaian
  nyata (persis simulasi "admin_primer login lalu isi data lewat UI").
- Akun `admin@example.com` (super_admin) tetap dibuat terpisah SEBELUM
  koperasi ada, jadi `koperasi_id`-nya tetap `null` (platform-level,
  bukan milik tenant manapun) — sesuai desain.
- `UserSeeder` disederhanakan (cuma bikin 6 akun ber-role "Staff" contoh,
  entry "Administrator" lama sudah dipindah), `DemoStaffRoleSeeder` diisi
  `koperasi_id` eksplisit dari aktor yang sedang aktif (Role tidak pakai
  trait, lihat catatan Fase 6).
- Idempotensi dijaga eksplisit: provisioning koperasi+admin_primer di-skip
  kalau "Koperasi Demo" sudah ada (`KoperasiService::store()` sendiri
  selalu insert baru tanpa existence check, jadi re-seed tanpa guard ini
  akan gagal kena unique constraint email) — diverifikasi test re-seed
  yang sudah ada (`database seeder is idempotent...`) tetap hijau.
- `tests/Feature/DatabaseSeederTest.php` diperbarui: `roles` 2→3,
  `users` 7→8, plus assertion baru: `admin_primer` benar py role &
  permission (68 = 70 dikurangi `role.create`/`role.delete`) yang benar,
  dan SEMUA data domain (`unit_kerja`, `karyawan`, `barang`) benar-benar
  ter-tag ke koperasi demo (bukan `koperasi_id` null) — bukan cuma
  jumlahnya cocok.
- Diverifikasi juga langsung di MySQL sungguhan (`migrate:fresh --seed`):
  8 user (7 dalam koperasi + super_admin di luar), 15/15 karyawan ter-tag,
  3 role (`super_admin`, `admin_primer`, `Staff`).

### Fase 9 — Rollout
- [ ] Jalankan migration & seeder di environment staging, verifikasi manual
      — **belum dikerjakan**: butuh akses ke environment staging/production
      sungguhan (kredensial server, DB, dsb) yang tidak tersedia di sesi
      ini. Migration & seeder sudah teruji berulang kali di MySQL lokal
      (lihat catatan Fase 1–8) dan lewat `migrate:fresh --seed` — secara
      teknis siap dijalankan, tinggal dieksekusi di server sungguhan oleh
      yang punya akses.
- [ ] Buat akun `super_admin` pertama (production) — **belum dikerjakan**,
      sama seperti di atas (aksi produksi nyata, bukan sesuatu yang aman
      dilakukan dari sesi kerja ini tanpa konfirmasi eksplisit). Caranya
      sudah didokumentasikan di `docs/panduan-super-admin.md` §4.
- [x] Dokumentasikan alur operasional: cara Super Admin bikin koperasi
      baru, perpanjang `expires_at`, dan tambah role custom untuk koperasi
      tertentu — `docs/panduan-super-admin.md` (baru).

> **Temuan & perbaikan saat menulis dokumentasi ini**: alur "tambah role
> custom untuk koperasi tertentu" ternyata **belum benar-benar bisa
> dipakai** — form Tambah Role (`role/form.blade.php`) belum punya
> pemilih koperasi sama sekali, jadi role yang dibuat lewat situ selalu
> dapat `koperasi_id = null` dan tidak akan pernah muncul/bisa dipilih di
> koperasi manapun. Ini baru ketahuan karena mencoba menuliskan
> dokumentasi alur secara jujur (bukan cuma menyalin niat dari Fase 5).
> Diperbaiki sekalian:
> - `StoreRoleRequest` sekarang mewajibkan `koperasi_id` (unique nama role
>   dicek per-koperasi, bukan global — dua koperasi boleh sama-sama punya
>   role "Staff").
> - `UpdateRoleRequest` sengaja TIDAK menerima `koperasi_id` sama sekali
>   (role tidak boleh dipindah tenant lewat edit).
> - `RoleRepository::create()`/`RoleService::store()` diisi `koperasi_id`
>   eksplisit (pola sama seperti `KoperasiService` — set properti
>   langsung, karena Role tidak pakai trait `BelongsToKoperasi`).
> - `role/form.blade.php` — dropdown pilih koperasi, cuma muncul saat
>   membuat role baru (bukan saat edit).
> - `role/index.blade.php` — kolom "Koperasi" tambahan, cuma untuk
>   `super_admin` (supaya bisa membedakan role dengan nama sama milik
>   tenant berbeda; `admin_primer` tidak butuh ini karena listnya sudah
>   otomatis ter-scope ke koperasinya sendiri).
> - `App\Models\Role` dapat relasi `koperasi()` biasa (bukan lewat trait)
>   untuk keperluan tampilan di atas.
> - Beberapa test lama yang men-submit form Tambah Role tanpa
>   `koperasi_id` diperbaiki supaya tetap merepresentasikan alur nyata
>   (`AuthorizationTest`, `WriteTransactionTest`, `SuperAdminGuardTest`,
>   `LaporanPenggajianTest`).
> - Test baru: `tests/Feature/TenantIsolationTest.php` →
>   *"super_admin can provision a custom role for one specific koperasi,
>   and only that koperasi can see or assign it"* — membuktikan alur
>   penuh: super_admin bikin role untuk koperasi A, admin_primer koperasi
>   A bisa lihat & pakai role itu di form Pengguna, admin_primer koperasi
>   B sama sekali tidak melihatnya (termasuk 404 kalau coba akses
>   `role.edit` lewat URL langsung).
