# Rencana Pengembangan 1

## Portal Karyawan, Notifikasi Lintas Role, dan Kendali Maintenance Platform

## Status Implementasi Branch

Branch kerja: `feature/rencana-pengembangan-1` (tidak digabung ke `main` secara otomatis).

Status per 29 Agustus 2026:

- [x] Fondasi relasi satu-ke-satu opsional `User ↔ Karyawan`.
- [x] Validasi tenant, unique constraint, sinkronisasi unit kerja, dan audit link/unlink.
- [x] UI Admin Primer untuk menghubungkan dan melepas akun login karyawan.
- [x] Portal Data Saya.
- [x] Absensi Saya dalam mode read-only.
- [x] Slip Gaji Saya dengan status draf/diterbitkan dan audit akses.
- [x] Larangan petugas Keuangan memproses gajinya sendiri.
- [x] Notifikasi personal ketika slip gaji diterbitkan.
- [x] Pengumuman System Owner kepada seluruh Admin Primer atau satu koperasi.
- [x] Soft maintenance dengan bypass System Owner, jadwal, audit, response 503, dan recovery CLI.
- [ ] Pembuatan akun baru langsung dari detail karyawan dan invitation password.
- [ ] Pengajuan perubahan data, izin/cuti, dan approval berjenjang.
- [ ] Penanggung jawab aset dan halaman Aset Saya.
- [ ] Status akun aktif/nonaktif serta workflow offboarding lengkap.
- [ ] Redis/Horizon; tetap ditunda sampai metrik production menunjukkan kebutuhan.

Catatan verifikasi: source sudah diperiksa dengan `git diff --check`. Feature test dan build belum dapat dijalankan pada environment Codex ini karena binary PHP/Node tidak tersedia di host dan akses Docker daemon ditolak.

## 1. Ringkasan

Dokumen ini menjadi rencana pengembangan untuk menghubungkan akun login (`users`) dengan identitas kepegawaian (`karyawan`), membangun akses personal karyawan, memperluas notifikasi lintas role, serta menyediakan kendali maintenance platform bagi `system_owner`.

Fondasi utama pengembangan adalah pemisahan dua konteks berikut:

- `users.koperasi_id` menentukan afiliasi tenant dan ruang akses akun.
- `karyawan.user_id` menghubungkan akun login dengan data kepegawaian milik orang tersebut.

Relasi yang dipilih adalah satu-ke-satu opsional:

```text
Koperasi
 ├── banyak User
 └── banyak Karyawan

User 1 ─── 0..1 Karyawan
```

Foreign key ditempatkan pada tabel `karyawan`, bukan tabel pivot dan bukan `karyawan_id` pada `users`.

```text
users
├── id
├── koperasi_id
├── unit_kerja_id
├── name
├── email
└── password

karyawan
├── id
├── koperasi_id
├── user_id (nullable, unique)
├── unit_kerja_id
├── nik
├── nama_lengkap
└── data kepegawaian lainnya
```

Alasan keputusan ini:

- Akun platform seperti `system_owner` dan `super_admin` bukan karyawan tenant.
- Data karyawan dapat ada sebelum akun login dibuat.
- Tidak semua karyawan harus mempunyai akun login.
- Penghapusan akun tidak boleh menghapus histori kepegawaian.
- Satu akun tidak boleh mewakili lebih dari satu karyawan.
- Pivot tidak diperlukan karena kebutuhan saat ini bukan many-to-many.

## 2. Tujuan

- Menghubungkan akun tenant dengan satu identitas karyawan secara aman.
- Memungkinkan karyawan melihat data, absensi, dan slip gajinya sendiri.
- Memisahkan akses personal dari akses pekerjaan berbasis role/permission.
- Memungkinkan role seperti Keuangan mengelola penggajian orang lain tanpa otomatis memperoleh hak mengubah gajinya sendiri.
- Menyediakan fondasi approval, notifikasi personal, struktur atasan, dan employee self-service.
- Menyediakan pengumuman platform dan soft maintenance yang dikelola `system_owner`.
- Mempertahankan isolasi data antar-koperasi dan jejak audit untuk data sensitif.

## 3. Bukan Tujuan Tahap Awal

- Mendukung satu akun bekerja pada beberapa koperasi sekaligus.
- Menggunakan tabel pivot `karyawan_user`.
- Membangun sistem HR lengkap dalam satu rilis.
- Mengizinkan karyawan langsung mengubah data kepegawaian utama tanpa persetujuan.
- Menjadikan Redis sebagai prasyarat fitur.
- Menjalankan `php artisan down` langsung dari tombol web.
- Membuat seluruh notifikasi menjadi real-time sejak tahap pertama.

## 4. Pembagian Tanggung Jawab Role

### 4.1 System Owner

- Mengelola kesehatan dan operasional platform.
- Mengelola product request.
- Membuat pengumuman platform.
- Mengaktifkan dan menonaktifkan soft maintenance.
- Tetap dapat mengakses aplikasi saat soft maintenance aktif.
- Tidak otomatis memperoleh akses rinci ke data pribadi tenant.

### 4.2 Super Admin

- Mengelola control-plane lintas koperasi.
- Membuat/provisioning koperasi dan Admin Primer.
- Memantau status dan masa aktif koperasi.
- Tidak mengendalikan maintenance teknis platform.

### 4.3 Admin Primer

- Mengelola pengguna dan role dalam koperasinya.
- Mengelola data karyawan dalam koperasinya.
- Menghubungkan akun tenant dengan data karyawan.
- Mengelola atau menyetujui workflow tenant sesuai permission.

### 4.4 Role Tenant

- Menerima akses operasional berdasarkan permission, misalnya Keuangan, SDM, atau pengelola inventaris.
- Tetap memiliki akses personal ke data miliknya terlepas dari role operasionalnya.

## 5. Aturan Domain dan Keamanan

### 5.1 Invariant Relasi Akun–Karyawan

- `karyawan.user_id` boleh `null`.
- `karyawan.user_id` harus unik jika terisi.
- User yang ditautkan harus berasal dari koperasi yang sama dengan karyawan.
- `system_owner` dan `super_admin` tidak boleh ditautkan ke karyawan tenant.
- Akun tanpa `koperasi_id` tidak boleh ditautkan.
- Satu karyawan hanya boleh ditautkan ke satu user.
- Satu user hanya boleh ditautkan ke satu karyawan.
- Pelepasan hubungan tidak menghapus user atau karyawan.
- Penghapusan user membuat `karyawan.user_id` menjadi `null`.
- Penghapusan karyawan tidak otomatis menghapus akun login.
- Semua aksi hubungkan/lepas akun harus diaudit.

Validasi penting pada service:

```php
if ((int) $user->koperasi_id !== (int) $karyawan->koperasi_id) {
    throw new DomainException('Akun dan karyawan harus berasal dari koperasi yang sama.');
}
```

### 5.2 Akses Personal dan Akses Operasional

Akses personal ditentukan oleh kepemilikan record:

```php
$isOwnRecord = (int) $record->karyawan->user_id === (int) $user->id;
```

Akses operasional ditentukan oleh permission:

```php
$canManage = $user->can('transaksi-gaji.view');
```

Keduanya tidak boleh dicampur. Contoh:

- Karyawan biasa dapat melihat slip miliknya tanpa `transaksi-gaji.view`.
- Keuangan dapat membuat transaksi gaji orang lain dengan `transaksi-gaji.create`.
- Keuangan tidak otomatis boleh mengubah gajinya sendiri.
- Membaca slip sendiri tidak memberi hak melihat daftar penggajian seluruh koperasi.
- Endpoint personal harus mengambil identitas karyawan dari user login, bukan menerima `karyawan_id` bebas dari request.

### 5.3 Data Sensitif

- Slip gaji hanya tersedia setelah diterbitkan.
- Dokumen karyawan harus melalui authorization, bukan URL storage publik.
- Nomor KTP, NPWP, rekening, dan data keluarga perlu masking sesuai konteks.
- Akses baca terhadap slip dan dokumen sensitif perlu dicatat.
- Payload notifikasi tidak boleh memuat nominal gaji, isi dokumen, catatan internal, atau data pribadi berlebihan.
- URL notifikasi wajib mengarah ke host aplikasi dan record yang dapat diakses penerima.

### 5.4 Konflik Kepentingan Penggajian

- Role Keuangan boleh menginput gaji orang lain jika memiliki permission.
- Keuangan tidak boleh membuat/mengubah/menerbitkan gajinya sendiri tanpa pengecualian yang eksplisit.
- Pembuat dan penyetuju transaksi idealnya bukan orang yang sama.
- Semua perubahan komponen dan nominal harus memiliki audit trail.
- Penghapusan transaksi yang sudah diterbitkan harus dibatasi atau diganti mekanisme pembatalan.

## 6. Keputusan Sumber Kebenaran Data

Saat ini `users` dan `karyawan` sama-sama memiliki `unit_kerja_id`. Hal ini berpotensi menghasilkan data tidak konsisten.

Rekomendasi:

- `karyawan.unit_kerja_id` menjadi sumber kebenaran untuk identitas kepegawaian.
- `users.unit_kerja_id` dipertahankan sementara untuk kompatibilitas.
- Saat akun ditautkan atau karyawan dimutasi, `users.unit_kerja_id` disinkronkan melalui service.
- Tambahkan pemeriksaan konsistensi dan laporan akun yang unitnya berbeda.
- Evaluasi penghapusan `users.unit_kerja_id` hanya setelah seluruh pemakaian lama dimigrasikan.

## 7. Tahapan Implementasi

## Fase 0 — Audit dan Kontrak Perilaku

Tujuan fase ini adalah membekukan perilaku keamanan yang sudah ada sebelum perubahan schema dan policy.

### Checklist

- [ ] Petakan seluruh query `User`, `Karyawan`, `Absensi`, dan `TransaksiGaji` yang bergantung pada `koperasi_id`.
- [ ] Petakan seluruh route personal dan operasional yang akan terdampak.
- [ ] Dokumentasikan permission yang saat ini diberikan kepada Admin Primer dan role Staff demo.
- [ ] Tambahkan tes regresi isolasi tenant sebelum mengubah relasi.
- [ ] Tambahkan tes bahwa tenant A tidak dapat memilih user/karyawan tenant B melalui manipulasi ID.
- [ ] Tambahkan tes bahwa akun platform tidak dapat diperlakukan sebagai karyawan.
- [ ] Putuskan apakah Admin Primer boleh menautkan akun sendiri sebagai karyawan.
- [ ] Putuskan apakah penghapusan karyawan dengan akun tertaut dilarang atau otomatis melepas tautan.
- [ ] Putuskan apakah akun karyawan dibuat dari menu Karyawan atau hanya ditautkan dari akun yang sudah ada.
- [ ] Putuskan status awal fitur melalui feature flag bila rollout bertahap diperlukan.

## Fase 1 — Fondasi Relasi User–Karyawan

### File baru

- `database/migrations/xxxx_xx_xx_xxxxxx_add_user_id_to_karyawan_table.php`
- `app/Http/Controllers/KaryawanAccountController.php`
- `app/Http/Requests/Karyawan/LinkUserRequest.php`
- `app/Services/KaryawanAccountService.php`
- `tests/Feature/KaryawanAccountTest.php`

### File yang diubah

- `app/Models/User.php`
- `app/Models/Karyawan.php`
- `app/Policies/KaryawanPolicy.php`
- `app/Support/PermissionCatalog.php`
- `app/Repositories/KaryawanRepository.php`
- `routes/web.php`
- `resources/views/karyawan/show.blade.php`
- migration/seed pemberian permission pada role Admin Primer yang sudah ada

### Desain migration

```php
Schema::table('karyawan', function (Blueprint $table) {
    $table->foreignId('user_id')
        ->nullable()
        ->unique()
        ->after('koperasi_id')
        ->constrained('users')
        ->nullOnDelete();
});
```

### Relasi model

```php
// User.php
public function karyawan(): HasOne
{
    return $this->hasOne(Karyawan::class);
}

// Karyawan.php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

### Checklist schema dan model

- [ ] Tambahkan kolom `karyawan.user_id` nullable.
- [ ] Tambahkan unique constraint pada `user_id`.
- [ ] Tambahkan foreign key `nullOnDelete` ke `users`.
- [ ] Tambahkan index yang dibutuhkan untuk lookup akun–karyawan.
- [ ] Tambahkan `user_id` ke `$fillable` hanya jika assignment tetap dikontrol service; jika tidak, jangan mass-assign.
- [ ] Tambahkan relasi `User::karyawan()`.
- [ ] Tambahkan relasi `Karyawan::user()`.
- [ ] Tambahkan eager loading akun hanya pada halaman yang membutuhkannya.
- [ ] Pastikan relasi baru tidak menimbulkan rekursi auth/global scope.

### Checklist service dan validasi

- [ ] Implementasikan `link(User $actor, Karyawan $karyawan, User $target)`.
- [ ] Implementasikan `unlink(User $actor, Karyawan $karyawan)`.
- [ ] Validasi aktor memiliki hak mengelola akun karyawan.
- [ ] Validasi target merupakan akun tenant.
- [ ] Validasi target bukan System Owner atau Super Admin.
- [ ] Validasi `target.koperasi_id === karyawan.koperasi_id`.
- [ ] Validasi target belum terhubung ke karyawan lain.
- [ ] Jalankan link/unlink dalam database transaction.
- [ ] Sinkronkan `users.unit_kerja_id` dari karyawan jika keputusan sumber kebenaran sudah disetujui.
- [ ] Catat audit actor, target user, karyawan, waktu, dan jenis aksi.
- [ ] Hindari menerima `koperasi_id` dari form sebagai sumber otoritatif.

### Checklist UI dan route

- [ ] Tambahkan route hubungkan akun.
- [ ] Tambahkan route lepas akun.
- [ ] Lindungi route dengan auth, tenant aktif, policy, dan CSRF.
- [ ] Tampilkan status akun pada detail karyawan.
- [ ] Tampilkan pilihan akun hanya dari koperasi aktif.
- [ ] Kecualikan akun platform dan akun yang sudah tertaut.
- [ ] Tambahkan konfirmasi sebelum melepas akun.
- [ ] Jangan menampilkan email user tenant lain pada hasil pencarian.
- [ ] Tampilkan pesan validasi yang jelas ketika akun sudah dipakai.

### Checklist pengujian

- [ ] Admin Primer dapat menautkan akun tenant miliknya.
- [ ] Aktor tanpa permission ditolak.
- [ ] User dan karyawan beda koperasi ditolak.
- [ ] System Owner dan Super Admin ditolak sebagai target.
- [ ] Satu user tidak dapat ditautkan ke dua karyawan.
- [ ] Satu karyawan tidak dapat memiliki dua user.
- [ ] Link ulang ke user yang sama bersifat idempotent atau memberi pesan yang aman.
- [ ] Pelepasan akun tidak menghapus kedua record.
- [ ] Penghapusan user mengosongkan `karyawan.user_id`.
- [ ] Manipulasi route model binding lintas tenant menghasilkan 404/403.
- [ ] Unit kerja tersinkron sesuai keputusan domain.
- [ ] Database rollback bekerja jika audit/sinkronisasi gagal.

## Fase 2 — Pembuatan Akun dari Karyawan

Fase ini opsional tetapi memudahkan onboarding karyawan yang belum mempunyai akun.

### File potensial

- `app/Http/Requests/Karyawan/CreateKaryawanAccountRequest.php`
- `app/Notifications/KaryawanAccountInvitation.php`
- penambahan aksi pada `KaryawanAccountController`
- penambahan metode pada `KaryawanAccountService`
- penambahan pengujian pada `KaryawanAccountTest`

### Alur

```text
Admin Primer membuka detail karyawan
→ memilih "Buat akun login"
→ mengisi email dan role awal
→ sistem membuat user dalam koperasi yang sama
→ sistem menautkan user ke karyawan
→ sistem mengirim undangan pengaturan password
→ user mengaktifkan akun
```

### Checklist

- [ ] Tambahkan tombol “Buat akun login” hanya untuk karyawan tanpa akun.
- [ ] Validasi email unik secara global.
- [ ] Batasi role pilihan ke role tenant dari koperasi yang sama.
- [ ] Larang assignment role sistem (`system_owner`, `super_admin`, `admin_primer`) melalui form biasa.
- [ ] Buat user, assign role, dan link karyawan dalam satu transaction.
- [ ] Jangan mengirim password plaintext melalui email.
- [ ] Gunakan invitation/reset-password token dengan masa berlaku.
- [ ] Catat siapa yang membuat akun.
- [ ] Kirim notifikasi hanya setelah transaction commit.
- [ ] Tangani kegagalan email tanpa membatalkan akun yang sudah valid, atau antrekan retry yang jelas.
- [ ] Tambahkan halaman status undangan.
- [ ] Tambahkan opsi kirim ulang undangan dengan throttling.
- [ ] Uji rollback jika role/link gagal.
- [ ] Uji user tidak dapat mengaktifkan token milik akun lain.

## Fase 3 — Portal “Data Saya”

### File baru yang disarankan

- `app/Http/Controllers/MyProfileController.php`
- `resources/views/me/profile.blade.php`
- `tests/Feature/MyProfileTest.php`

### Checklist

- [ ] Tambahkan route `/saya` atau `/saya/profil`.
- [ ] Resolve karyawan hanya melalui `$request->user()->karyawan`.
- [ ] Tampilkan profil, unit kerja, jabatan, atasan, dan status kerja.
- [ ] Masking data sensitif sesuai kebutuhan.
- [ ] Jangan memakai route detail karyawan operasional untuk portal personal.
- [ ] Sediakan empty state bagi akun tenant yang belum ditautkan.
- [ ] Beri petunjuk menghubungi Admin Primer jika tautan belum tersedia.
- [ ] Pastikan user tidak dapat mengganti ID karyawan melalui query string.
- [ ] Tambahkan tes akses personal dan isolasi tenant.

## Fase 4 — Absensi Saya

### File baru yang disarankan

- `app/Http/Controllers/MyAttendanceController.php`
- `resources/views/me/attendance.blade.php`
- `tests/Feature/MyAttendanceTest.php`

### File yang mungkin diubah

- `app/Services/AbsensiService.php`
- `app/Support/NavigationMenu.php`
- komponen kalender absensi agar dapat dipakai ulang

### Checklist

- [ ] Tambahkan route `GET /saya/absensi`.
- [ ] Ambil karyawan dari relasi user login.
- [ ] Tampilkan kalender hanya milik user tersebut.
- [ ] Pisahkan mode read-only personal dari mode input absensi operasional.
- [ ] Jangan memberikan `absensi.create` hanya agar karyawan dapat melihat absensinya.
- [ ] Tampilkan ringkasan hadir, izin, sakit, alpa, dan dinas luar kota.
- [ ] Tambahkan navigasi bulan/tahun dengan batas input yang aman.
- [ ] Tambahkan empty state jika belum ada data.
- [ ] Uji user biasa tidak dapat melihat absensi karyawan lain.
- [ ] Uji role Keuangan tetap hanya melihat absensi sendiri melalui route personal.
- [ ] Uji role yang memiliki `absensi.view` tetap dapat memakai modul operasional sesuai permission.

## Fase 5 — Slip Gaji Saya

### File baru yang disarankan

- `app/Http/Controllers/MySalarySlipController.php`
- `resources/views/me/salary-slips/index.blade.php`
- `resources/views/me/salary-slips/show.blade.php`
- `tests/Feature/MySalarySlipTest.php`

### File yang mungkin diubah

- `app/Models/TransaksiGaji.php`
- `app/Policies/TransaksiGajiPolicy.php`
- `app/Services/TransaksiGajiService.php`
- `app/Http/Controllers/TransaksiGajiController.php` untuk ekstraksi renderer bersama
- `app/Support/NavigationMenu.php`

### Perubahan domain yang disarankan

Tambahkan status publikasi transaksi/slip, misalnya:

```text
draft → submitted → approved → published → cancelled
```

Untuk versi awal minimal dapat menggunakan:

```text
draft → published
```

### Checklist

- [ ] Tambahkan status publikasi atau field `published_at`.
- [ ] Simpan `published_by` untuk audit.
- [ ] Tambahkan route daftar slip pribadi.
- [ ] Tambahkan route detail/unduh slip pribadi.
- [ ] Filter hanya transaksi milik `auth.user.karyawan`.
- [ ] Tampilkan hanya transaksi yang sudah diterbitkan.
- [ ] Jangan memuat transaksi draft melalui route personal.
- [ ] Authorization detail wajib memeriksa `transaksi.karyawan.user_id`.
- [ ] Gunakan response download/stream yang aman.
- [ ] Catat akses lihat dan unduh slip.
- [ ] Kirim notifikasi setelah slip diterbitkan.
- [ ] Payload notifikasi tidak memuat nominal gaji.
- [ ] Uji manipulasi ID transaksi milik karyawan lain.
- [ ] Uji Keuangan dapat melihat slip sendiri tetapi tidak otomatis melihat semua slip melalui route personal.
- [ ] Uji transaksi draft tidak dapat diakses dengan URL langsung.

## Fase 6 — Role Keuangan dan Workflow Penggajian

### File yang mungkin diubah

- `app/Policies/TransaksiGajiPolicy.php`
- `app/Http/Requests/TransaksiGaji/StoreTransaksiGajiRequest.php`
- `app/Http/Requests/TransaksiGaji/UpdateTransaksiGajiRequest.php`
- `app/Services/TransaksiGajiService.php`
- `app/Support/PermissionCatalog.php`
- migration grant permission jika ada permission baru
- pengujian authorization penggajian

### Checklist

- [ ] Definisikan permission view/create/update/delete/approve/publish secara terpisah.
- [ ] Tentukan template permission untuk role Keuangan.
- [ ] Cegah Keuangan membuat atau mengubah gajinya sendiri.
- [ ] Cegah pembuat menyetujui transaksi yang dibuatnya sendiri jika workflow approval diterapkan.
- [ ] Simpan `created_by`, `updated_by`, `approved_by`, dan `published_by`.
- [ ] Catat perubahan komponen gaji sebelum dan sesudah.
- [ ] Batasi penghapusan transaksi published.
- [ ] Tambahkan mekanisme pembatalan/koreksi untuk transaksi final.
- [ ] Kirim notifikasi kepada penyetuju saat transaksi diajukan.
- [ ] Kirim notifikasi kepada karyawan saat slip diterbitkan.
- [ ] Uji matriks akses Admin Primer, Keuangan, karyawan biasa, dan tenant lain.

## Fase 7 — Pengajuan Mandiri dan Approval Berjenjang

Potensi workflow:

- perubahan data pribadi;
- izin, sakit, dan cuti;
- koreksi absensi;
- lembur;
- perjalanan dinas;
- reimbursement;
- peminjaman atau serah-terima aset.

Alur umum:

```text
Karyawan mengajukan
→ Atasan langsung memeriksa
→ SDM/Admin Primer memfinalisasi
→ data utama diperbarui
→ semua pihak menerima notifikasi
```

### Checklist fondasi approval

- [ ] Tentukan apakah menggunakan tabel approval generik atau tabel khusus per domain.
- [ ] Definisikan status `draft`, `submitted`, `approved`, `rejected`, `cancelled`.
- [ ] Simpan pemohon, approver, waktu, komentar, dan alasan penolakan.
- [ ] Resolve atasan dari `karyawan.atasan_langsung_id`.
- [ ] Pastikan atasan memiliki akun login aktif.
- [ ] Sediakan fallback approver jika atasan tidak aktif/tidak memiliki akun.
- [ ] Cegah self-approval.
- [ ] Cegah perubahan payload setelah pengajuan tanpa membuat revisi.
- [ ] Catat audit seluruh transisi.
- [ ] Kirim notifikasi setelah commit.
- [ ] Uji pergantian atasan ketika request masih berjalan.
- [ ] Uji approver lintas tenant selalu ditolak.

## Fase 8 — Inventaris Personal dan Penanggung Jawab Aset

Relasi aset sebaiknya diarahkan ke `karyawan_id`, bukan `user_id`, agar histori tetap ada saat akun login dihapus.

### Potensi fitur

- serah-terima barang;
- penanggung jawab/pemegang barang;
- konfirmasi penerimaan;
- pelaporan rusak atau hilang;
- pengingat pengembalian;
- audit inventaris berkala;
- pengecekan aset saat offboarding.

### Checklist

- [ ] Definisikan model histori penugasan aset.
- [ ] Simpan pemegang, pemberi, tanggal serah, tanggal kembali, dan kondisi.
- [ ] Jangan hanya menimpa pemegang terakhir tanpa histori.
- [ ] Tambahkan halaman “Aset Saya”.
- [ ] Kirim notifikasi saat aset diberikan atau diminta kembali.
- [ ] Minta konfirmasi penerimaan bila diperlukan.
- [ ] Cegah penyelesaian offboarding jika aset belum kembali, atau berikan override beraudit.
- [ ] Uji bahwa user hanya melihat aset yang ditugaskan kepadanya.

## Fase 9 — Lifecycle dan Offboarding Karyawan

### Perubahan yang disarankan

Tambahkan status akun, misalnya `users.is_active` atau `users.status`, agar akses dapat dicabut tanpa menghapus histori user.

### Checklist

- [ ] Tambahkan status aktif/nonaktif akun.
- [ ] Blokir login akun nonaktif.
- [ ] Putus sesi aktif ketika akun dinonaktifkan.
- [ ] Saat `tanggal_mengundurkan_diri` diisi, tampilkan checklist offboarding.
- [ ] Periksa aset yang belum dikembalikan.
- [ ] Periksa approval/tugas yang masih tertunda.
- [ ] Cabut role operasional sesuai kebijakan.
- [ ] Pertahankan relasi atau histori link untuk audit.
- [ ] Hentikan notifikasi rutin ke akun nonaktif.
- [ ] Pertahankan slip dan histori lama sesuai kebijakan retensi.
- [ ] Catat siapa yang menonaktifkan dan alasannya.
- [ ] Sediakan mekanisme reaktivasi beraudit jika diperlukan.

## Fase 10 — Sistem Notifikasi yang Diperluas

Notifikasi harus berfokus pada informasi yang membutuhkan perhatian atau tindakan, bukan setiap aktivitas CRUD.

### Matriks awal notifikasi

| Peristiwa | Pengirim/Sumber | Penerima | Prioritas |
|---|---|---|---|
| Slip gaji diterbitkan | Keuangan/Admin Primer | Karyawan terkait | Informasi |
| Pengajuan dibuat | Karyawan | Atasan/approver | Perlu tindakan |
| Pengajuan disetujui/ditolak | Approver | Karyawan terkait | Informasi |
| Koreksi absensi diajukan | Karyawan | Atasan/SDM | Perlu tindakan |
| Barang ditugaskan | Pengelola inventaris | Karyawan terkait | Perlu tindakan |
| Barang dilaporkan rusak/hilang | Karyawan | Admin Primer/pengelola inventaris | Kritis |
| Masa aktif koperasi mendekati akhir | Sistem | Admin Primer dan Super Admin | Peringatan |
| Koperasi kedaluwarsa | Sistem | Admin Primer dan Super Admin | Kritis |
| Pengumuman platform | System Owner | Target pengumuman | Sesuai pilihan |
| Maintenance dijadwalkan | System Owner | Pengguna terdampak | Peringatan |

### Struktur notifikasi yang disarankan

- kategori;
- event/type;
- tingkat prioritas;
- judul ringkas;
- ringkasan aman;
- URL tujuan;
- `read_at`;
- opsional `actioned_at`;
- metadata minimal yang tidak sensitif.

### Checklist

- [ ] Buat katalog event notifikasi untuk mencegah string tersebar.
- [ ] Pisahkan notifikasi personal, pengumuman, dan digest.
- [ ] Tentukan recipient resolver untuk setiap event.
- [ ] Pastikan recipient resolver selalu tenant-aware.
- [ ] Tolak penerima dari tenant lain.
- [ ] Hindari mengirim notifikasi kepada actor sendiri kecuali dibutuhkan.
- [ ] Gunakan event/listener agar logic domain tidak bergantung pada channel notifikasi.
- [ ] Dispatch notifikasi hanya setelah transaction commit.
- [ ] Sanitasi payload notifikasi.
- [ ] Tambahkan kategori/filter pada halaman notifikasi bila volume meningkat.
- [ ] Tambahkan `actioned_at` untuk notifikasi yang membutuhkan tindakan.
- [ ] Tambahkan retention/cleanup policy notifikasi lama.
- [ ] Uji recipient, payload, URL, read-all, dan ownership endpoint.
- [ ] Tambahkan channel email hanya untuk event penting dan dapat dikonfigurasi.
- [ ] Gunakan digest untuk event berulang agar inbox tidak penuh.

## Fase 11 — Pengumuman Platform oleh System Owner

Pengumuman massal sebaiknya memiliki model sendiri, bukan langsung membuat ribuan row notifikasi pada saat disimpan.

### Model data potensial

```text
announcements
├── id
├── title
├── body
├── priority
├── starts_at
├── ends_at
├── published_at
├── created_by
└── timestamps

announcement_targets
├── announcement_id
├── target_type
└── target_id / target_scope
```

Target dapat berupa:

- semua Admin Primer;
- satu koperasi;
- beberapa koperasi;
- semua pengguna tenant;
- role tertentu dalam koperasi tertentu.

### Checklist

- [ ] Definisikan model dan target pengumuman.
- [ ] Batasi create/update/publish hanya untuk System Owner.
- [ ] Tambahkan draft, jadwal tayang, publish, dan archive.
- [ ] Validasi periode mulai/selesai.
- [ ] Sanitasi konten dan hindari HTML berbahaya.
- [ ] Tampilkan preview jumlah penerima tanpa mengekspos identitas tenant lain.
- [ ] Catat audit penerbitan dan perubahan target.
- [ ] Tentukan apakah pengumuman harus diakui/dibaca.
- [ ] Tampilkan banner hanya selama periode aktif.
- [ ] Kirim notifikasi ringkas saat pengumuman terbit.
- [ ] Uji targeting tenant dan role secara ketat.

## Fase 12 — Soft Maintenance oleh System Owner

Soft maintenance berbeda dari `php artisan down`:

- Soft maintenance: aplikasi tetap hidup; middleware memblokir semua akun selain System Owner.
- Hard maintenance: aplikasi diturunkan oleh deployment/server dan tidak dikendalikan dari UI.

### Model data potensial

```text
platform_settings / maintenance_windows
├── maintenance_enabled
├── maintenance_message
├── maintenance_started_at
├── maintenance_ends_at
├── maintenance_enabled_by
└── maintenance_disabled_by
```

### File baru yang disarankan

- migration konfigurasi/window maintenance
- `app/Models/MaintenanceWindow.php` atau service pengaturan platform
- `app/Services/PlatformMaintenanceService.php`
- `app/Http/Middleware/EnsurePlatformAvailable.php`
- `app/Http/Controllers/Owner/MaintenanceController.php`
- `app/Http/Requests/Owner/UpdateMaintenanceRequest.php`
- `resources/views/maintenance.blade.php`
- view panel maintenance pada area owner
- `tests/Feature/PlatformMaintenanceTest.php`

### Checklist

- [ ] Pilih model satu status aktif atau histori maintenance window.
- [ ] Hanya System Owner dapat mengaktifkan/nonaktifkan.
- [ ] Sediakan pesan dan estimasi selesai.
- [ ] Sediakan jadwal mulai/selesai opsional.
- [ ] Tambahkan middleware global setelah kebutuhan identitas dapat ditentukan.
- [ ] Izinkan route login/logout yang diperlukan.
- [ ] Setelah login, hanya System Owner yang dapat melewati soft maintenance.
- [ ] Kembalikan HTTP 503 untuk request pengguna yang diblokir.
- [ ] Berikan response JSON 503 untuk API/AJAX.
- [ ] Tambahkan `Retry-After` bila estimasi tersedia.
- [ ] Jangan menghapus session pengguna lain hanya untuk maintenance.
- [ ] Catat aktivasi, deaktivasi, actor, pesan, dan jadwal dalam audit log.
- [ ] Kirim notifikasi sebelum maintenance terjadwal.
- [ ] Sediakan command CLI untuk force enable/disable sebagai jalur pemulihan.
- [ ] Pastikan cache maintenance tidak membuat System Owner ikut terkunci.
- [ ] Uji guest, tenant user, Super Admin, dan System Owner.
- [ ] Uji scheduled start/end dan timezone Asia/Jakarta.
- [ ] Uji aplikasi tetap dapat dipulihkan ketika UI owner bermasalah.
- [ ] Dokumentasikan bahwa hard maintenance tetap dijalankan pipeline/server.

## Fase 13 — Redis, Queue, dan Skalabilitas Production

Redis belum menjadi prasyarat. Konfigurasi awal dapat tetap menggunakan database:

```env
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Notifikasi database tetap disimpan pada tabel `notifications`. Queue hanya memindahkan pekerjaan pengiriman agar request pengguna tidak menunggu.

### Urutan adopsi

1. Gunakan database notification.
2. Pindahkan pengiriman email/notifikasi berat ke queue.
3. Jalankan database queue worker menggunakan Supervisor/systemd.
4. Pantau pending job, failed job, usia job tertua, dan waktu proses.
5. Tambahkan Redis jika metrik menunjukkan kebutuhan.
6. Pertimbangkan Laravel Horizon ketika Redis queue digunakan secara serius.

### Redis layak diterapkan ketika

- notifikasi/email massal meningkat;
- export/import dan laporan berjalan di background;
- cache dashboard sering diakses;
- terdapat beberapa instance aplikasi;
- database mulai terbebani oleh job, cache, atau session;
- dibutuhkan broadcasting/WebSocket real-time;
- latency database queue mulai mengganggu.

### Checklist kesiapan queue

- [ ] Tentukan job yang harus queued dan yang tetap synchronous.
- [ ] Implementasikan idempotency untuk job penting.
- [ ] Dispatch job setelah commit.
- [ ] Hindari serialisasi model/payload sensitif berlebihan.
- [ ] Atur retry, backoff, timeout, dan `retry_after` secara konsisten.
- [ ] Pisahkan queue kritis dan queue massal bila volume meningkat.
- [ ] Konfigurasi worker production dan auto-restart saat deploy.
- [ ] Monitor failed job tanpa mengekspos payload rahasia.
- [ ] Tambahkan alert backlog dan failed job.
- [ ] Uji graceful degradation jika Redis/queue tidak tersedia.
- [ ] Dokumentasikan prosedur retry dan penanganan poison job.

## 8. File Minimum untuk Implementasi Pertama

### File baru

```text
database/migrations/xxxx_xx_xx_xxxxxx_add_user_id_to_karyawan_table.php
app/Http/Controllers/KaryawanAccountController.php
app/Http/Requests/Karyawan/LinkUserRequest.php
app/Services/KaryawanAccountService.php
tests/Feature/KaryawanAccountTest.php
```

### File diubah

```text
app/Models/User.php
app/Models/Karyawan.php
app/Policies/KaryawanPolicy.php
app/Support/PermissionCatalog.php
app/Repositories/KaryawanRepository.php
routes/web.php
resources/views/karyawan/show.blade.php
database/migrations/<grant-permission-ke-role-existing>.php
```

Implementasi pertama dianggap selesai jika Admin Primer dapat menghubungkan dan melepaskan akun tenant dengan karyawan secara aman, tanpa membuka akses personal absensi/gaji terlebih dahulu.

## 9. Strategi Migrasi Data Lama

Tidak disarankan mencocokkan akun dan karyawan hanya berdasarkan nama karena rawan salah tautan.

### Pilihan aman

- Tambahkan `user_id` dalam keadaan nullable.
- Biarkan seluruh record lama tetap `null`.
- Sediakan UI Admin Primer untuk menghubungkan secara manual.
- Jika ada email unik pada data karyawan di masa depan, sediakan command dry-run untuk kandidat pencocokan.
- Setiap hasil pencocokan otomatis harus dapat ditinjau sebelum diterapkan.

### Checklist

- [ ] Backup database sebelum migration production.
- [ ] Jalankan migration tanpa mengunci tabel terlalu lama.
- [ ] Pastikan migration rollback aman.
- [ ] Buat laporan karyawan tanpa akun.
- [ ] Buat laporan akun tenant tanpa karyawan.
- [ ] Buat laporan kandidat unit kerja yang tidak konsisten.
- [ ] Hindari backfill berdasarkan nama saja.
- [ ] Audit seluruh link manual/otomatis.

## 10. Strategi Pengujian Menyeluruh

### Unit test

- [ ] Validasi aturan relasi akun–karyawan.
- [ ] Recipient resolver notifikasi.
- [ ] Policy ownership personal.
- [ ] Transisi workflow gaji/approval.
- [ ] Penentuan status maintenance.

### Feature test

- [ ] Link/unlink akun.
- [ ] Portal Data Saya.
- [ ] Absensi Saya.
- [ ] Slip Gaji Saya.
- [ ] Role Keuangan mengelola gaji orang lain.
- [ ] Role Keuangan dilarang mengelola gaji sendiri.
- [ ] Approval berjenjang.
- [ ] Notifikasi penerima yang benar.
- [ ] Maintenance memblokir semua kecuali System Owner.

### Security regression

- [ ] Tenant A tidak dapat membaca/mengubah data tenant B.
- [ ] User tidak dapat mengganti `karyawan_id` untuk mengakses data orang lain.
- [ ] URL slip langsung tetap menjalankan policy.
- [ ] Dokumen tidak dapat diakses lewat path publik.
- [ ] Akun platform tidak dapat ditautkan ke karyawan.
- [ ] Permission tenant tidak dapat memberi identitas System Owner/Super Admin.
- [ ] Payload notifikasi tidak mengandung data sensitif.
- [ ] Soft maintenance tidak dapat diubah melalui request non-owner.

### Integration dan browser test

- [ ] Navigasi personal muncul untuk user yang tertaut.
- [ ] Empty state muncul untuk user yang belum tertaut.
- [ ] Badge notifikasi diperbarui dengan benar.
- [ ] Link notifikasi menuju halaman yang berwenang.
- [ ] Tampilan maintenance responsif dan jelas.
- [ ] Form link akun tetap usable pada jumlah user yang besar.

## 11. Observability dan Audit

### Event yang wajib diaudit

- akun dihubungkan/dilepas dari karyawan;
- akun dibuat dari data karyawan;
- akun dinonaktifkan/diaktifkan;
- slip dilihat, diunduh, disetujui, dan diterbitkan;
- perubahan nominal dan komponen gaji;
- approval dan penolakan;
- aset diserahkan/dikembalikan;
- pengumuman diterbitkan;
- maintenance diaktifkan/dinonaktifkan.

### Metrik yang disarankan

- jumlah karyawan tanpa akun;
- jumlah akun tenant tanpa karyawan;
- jumlah mismatch unit kerja;
- jumlah slip belum dilihat;
- jumlah approval tertunda;
- jumlah notifikasi gagal;
- pending/failed queue jobs;
- durasi maintenance;
- jumlah akun nonaktif dengan session aktif.

## 12. Rollout Production

### Tahap rollout

1. Deploy migration nullable dan relasi model.
2. Aktifkan UI link akun hanya untuk Admin Primer internal/pilot.
3. Tautkan beberapa akun pengujian secara manual.
4. Pantau audit dan error selama masa pilot.
5. Aktifkan Portal Data Saya.
6. Aktifkan Absensi Saya.
7. Tambahkan status published lalu aktifkan Slip Gaji Saya.
8. Aktifkan workflow Keuangan dan approval.
9. Aktifkan pengumuman serta soft maintenance System Owner.
10. Evaluasi Redis berdasarkan metrik nyata.

### Checklist sebelum produksi

- [ ] Seluruh migration diuji pada salinan database production.
- [ ] Backup dan prosedur restore tervalidasi.
- [ ] Tes isolasi tenant lulus.
- [ ] Tes ownership slip/absensi lulus.
- [ ] Permission role existing sudah dimigrasikan.
- [ ] Queue worker dan failed job monitoring siap jika notification queued.
- [ ] Audit log aktif.
- [ ] Dokumentasi Admin Primer dan System Owner tersedia.
- [ ] Jalur pemulihan maintenance melalui CLI diuji.
- [ ] Feature flag/rollback plan tersedia.

## 13. Definition of Done per Milestone

### Milestone A — Relasi akun–karyawan

- [ ] Schema, relasi, service, policy, route, dan UI selesai.
- [ ] Semua invariant tenant dan uniqueness ditegakkan.
- [ ] Audit link/unlink tersedia.
- [ ] Tes positif, negatif, dan lintas tenant lulus.

### Milestone B — Employee self-service

- [ ] Data Saya tersedia.
- [ ] Absensi Saya hanya menampilkan data pemilik.
- [ ] Slip Saya hanya menampilkan slip published milik pemilik.
- [ ] Akses personal tidak bergantung pada permission operasional.
- [ ] Audit akses data sensitif tersedia.

### Milestone C — Workflow operasional

- [ ] Role Keuangan mempunyai permission yang tepat.
- [ ] Konflik kepentingan gaji dicegah.
- [ ] Approval dan audit perubahan tersedia.
- [ ] Notifikasi dikirim kepada pihak yang tepat.

### Milestone D — Platform operations

- [ ] System Owner dapat membuat pengumuman tertarget.
- [ ] System Owner dapat mengaktifkan soft maintenance.
- [ ] Semua akun selain System Owner menerima 503 saat maintenance.
- [ ] CLI recovery dan audit maintenance tersedia.
- [ ] Hard maintenance tetap menjadi tanggung jawab deployment/server.

## 14. Risiko dan Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Salah tautan user–karyawan | Kebocoran absensi/slip | Link manual terotorisasi, konfirmasi identitas, audit, unique constraint |
| `koperasi_id` tidak sama | Kebocoran lintas tenant | Validasi service, scoped query, tes manipulasi ID |
| Unit kerja ganda tidak konsisten | Permission/dashboard salah | Tetapkan sumber kebenaran dan sinkronkan melalui service |
| Policy personal terlalu luas | User melihat data orang lain | Endpoint `/saya` khusus dan ownership check pada record |
| Keuangan mengubah gaji sendiri | Konflik kepentingan | Larangan self-processing dan approval terpisah |
| Slip draft terlihat | Informasi belum final bocor | Status published dan filter wajib pada route personal |
| Notifikasi berlebihan | Pengguna mengabaikan inbox | Prioritas, kategori, digest, hanya event actionable |
| System Owner terkunci saat maintenance | Tidak dapat memulihkan UI | Exemption middleware, route login aman, command CLI recovery |
| Tombol UI memakai hard maintenance | Aplikasi tidak dapat dinyalakan dari UI | Gunakan soft maintenance; hard maintenance via deployment |
| Queue/Redis gagal | Notifikasi tertunda/hilang | Retry, failed jobs, idempotency, observability, fallback terencana |

## 15. Urutan Prioritas yang Direkomendasikan

1. Fondasi relasi `karyawan.user_id`.
2. Audit dan laporan konsistensi akun–karyawan.
3. Portal Data Saya.
4. Absensi Saya.
5. Status publikasi dan Slip Gaji Saya.
6. Role Keuangan dan pencegahan self-processing.
7. Notifikasi personal.
8. Pengajuan serta approval berjenjang.
9. Penanggung jawab inventaris dan Aset Saya.
10. Lifecycle/offboarding.
11. Pengumuman platform oleh System Owner.
12. Soft maintenance oleh System Owner.
13. Redis dan real-time notification berdasarkan metrik production.

## 16. Catatan Keputusan Akhir

- Tidak menggunakan tabel pivot untuk hubungan user–karyawan pada kebutuhan saat ini.
- Foreign key yang dipilih adalah `karyawan.user_id`, nullable dan unique.
- `users.koperasi_id` tetap menjadi batas afiliasi dan akses tenant.
- `karyawan.koperasi_id` tetap menjadi pemilik data kepegawaian.
- Hak personal berasal dari ownership, bukan permission operasional.
- Hak pekerjaan tetap diberikan melalui role dan permission.
- System Owner mengelola pengumuman platform dan soft maintenance.
- Super Admin tetap berfokus pada administrasi lintas koperasi.
- Database queue cukup untuk tahap awal; Redis diterapkan berdasarkan kebutuhan dan metrik.
