# Panduan Operasional Super Admin

Panduan ini untuk vendor aplikasi (pemegang akun `super_admin`) yang mengelola
koperasi-koperasi pelanggan. Lihat `docs/multi-tenant-koperasi.md` untuk latar
belakang arsitektur multi-tenant-nya.

## 1. Membuat koperasi pelanggan baru

1. Login sebagai `super_admin`.
2. Buka **Administrasi → Manajemen Koperasi** di sidebar.
3. Klik **Tambah Koperasi**.
4. Isi:
   - **Nama Koperasi** — nama resmi pelanggan.
   - **Masa Aktif Sampai** — tanggal berakhirnya langganan. Kosongkan kalau
     ingin tanpa batas waktu (misalnya untuk koperasi internal/demo).
   - **Akun Admin Primer Pertama** (nama, email, password) — akun pertama
     yang akan dipegang koperasi ini. Sampaikan kredensial ini ke pelanggan
     lewat jalur aman di luar aplikasi (aplikasi tidak mengirim email
     otomatis).
5. Simpan. Sistem otomatis membuat:
   - Baris `koperasi` baru (`is_active = true`).
   - Role `admin_primer` khusus koperasi ini — permission penuh (semua
     modul bisnis) **kecuali** membuat/menghapus role dan mengangkat
     `super_admin`/`admin_primer` lain.
   - Akun pengguna pertama, langsung terikat ke koperasi ini dan ber-role
     `admin_primer`.

Setelah ini, admin_primer koperasi tersebut bisa login sendiri dan mulai
mengisi data (unit kerja, karyawan, barang, dst) — semuanya otomatis
ter-tag ke koperasinya, tidak pernah terlihat oleh koperasi lain.

## 2. Memperpanjang atau menghentikan masa aktif

1. **Administrasi → Manajemen Koperasi**.
2. Klik ikon edit pada koperasi yang dituju.
3. Ubah **Masa Aktif Sampai** ke tanggal baru, lalu simpan.
   - Begitu tanggal ini lewat, SEMUA pengguna di koperasi tsb (admin_primer
     & staf-nya) langsung diblokir login — mereka diarahkan ke halaman
     "Masa Aktif Berakhir" sampai diperpanjang.
4. Untuk menonaktifkan koperasi SEGERA (di luar soal tanggal, mis. karena
   tunggakan atau permintaan berhenti), hilangkan centang **Koperasi
   aktif** di form yang sama, lalu simpan. Efeknya sama: seluruh pengguna
   koperasi itu langsung diblokir login sampai dicentang aktif kembali.

Tidak ada penghapusan koperasi dari aplikasi — begitu koperasi punya
pengguna & data (selalu ada sejak dibuat), constraint database mencegah
penghapusan permanen. Nonaktifkan lewat langkah di atas, jangan mencoba
menghapus lewat database langsung.

## 3. Menambah role custom untuk satu koperasi tertentu

`admin_primer` **tidak bisa** membuat role baru sendiri (cuma bisa
mengatur ulang permission pada role yang sudah ada) — ini pembatasan
sengaja, bukan bug. Kalau sebuah koperasi butuh role tambahan (mis.
"Kasir", "Staf Gudang"), Super Admin yang membuatkannya:

1. **Administrasi → Role & Hak Akses**.
2. Klik **Tambah Role**.
3. Isi **Nama Role** (bebas, sesuai kebutuhan koperasi tsb) dan pilih
   **Koperasi** tujuan dari dropdown — role ini nantinya HANYA muncul dan
   bisa dipakai di koperasi yang dipilih di sini.
4. Centang permission yang sesuai, lalu simpan.

Setelah ini, admin_primer koperasi yang dituju akan melihat role baru
tersebut di halaman Role & Hak Akses mereka sendiri (dan bisa mengubah
permission-nya sendiri lewat sana), serta bisa memilihnya di dropdown role
saat menambah/mengedit pengguna. Koperasi lain tidak akan pernah melihat
role ini sama sekali.

> Catatan: nama role BOLEH sama persis dengan role di koperasi lain (mis.
> dua koperasi sama-sama punya role "Staff") — itu memang didukung, tidak
> akan saling tabrakan karena role selalu diikat ke satu koperasi.

Saat melihat daftar Role & Hak Akses sebagai `super_admin`, ada kolom
**Koperasi** tambahan untuk membedakan role milik tenant mana — kolom ini
tidak muncul untuk `admin_primer` karena mereka cuma pernah melihat role
di koperasinya sendiri.

## 4. Membuat akun `super_admin` tambahan

Saat ini belum ada halaman UI untuk membuat akun `super_admin` baru (akun
`super_admin` pertama dibuat manual saat instalasi, lihat
`docs/multi-tenant-koperasi.md` Fase 9). Kalau butuh menambah admin
platform lain, buat lewat `php artisan tinker` di server:

```php
$user = \App\Models\User::create([
    'name' => 'Nama Admin',
    'email' => 'admin-baru@contoh.test',
    'password' => Hash::make('password-aman'),
]);
$user->assignRole('super_admin');
```

(`koperasi_id` sengaja dibiarkan `null` — jangan diisi.)
