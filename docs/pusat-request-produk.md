# Pusat Request Produk

Fitur ini menjadi jalur resmi antara pengguna koperasi dan `system_owner` untuk permintaan fitur, laporan kendala, bantuan penggunaan, serta masukan produk lain. Implementasinya berada pada branch `feature/product-request-center` sampai proses review dan merge selesai.

## Akses pengguna koperasi

Permission yang tersedia:

- `product-request.view`
- `product-request.create`
- `product-request.reply`
- `product-request.close`

`admin_primer` memperoleh keempat permission secara default dan dapat melihat seluruh request dalam koperasinya. Role custom hanya dapat melihat request yang dibuat sendiri walaupun memiliki permission `view`; aturan ini mencegah percakapan anggota lain terbuka tanpa kebutuhan bisnis yang jelas.

Pengguna dapat:

1. Membuka menu **Request Produk**.
2. Memfilter tiket berdasarkan jenis dan status.
3. Mengajukan request dengan jenis, area aplikasi, judul, uraian, dampak, dan lampiran opsional.
4. Membalas percakapan publik selama status masih menerima informasi.
5. Menutup request aktif atau membuka kembali request yang sebelumnya ditutup sendiri.

Jangan masukkan password, token, atau data pribadi yang tidak diperlukan. Isi request merupakan data yang sengaja dibagikan kepada tim pengelola produk.

## Workflow system owner

`system_owner` membuka **Request Produk** dari workspace `/owner`. Owner tidak menerima permission tenant dan tidak memakai bypass tenant global; repository owner secara eksplisit hanya membuka tabel domain request produk.

Owner dapat:

- melihat inbox lintas koperasi;
- memfilter berdasarkan koperasi, jenis, status, prioritas, assignment, dan tanggal;
- mengirim balasan publik;
- membuat catatan internal;
- menentukan prioritas internal dan penanggung jawab;
- menjalankan transisi status yang diizinkan;
- menandai duplikat terhadap tiket lain dari koperasi yang sama;
- melihat history status yang immutable.

Catatan internal tidak tampil dalam timeline, jumlah lampiran, timestamp aktivitas publik, atau notifikasi tenant. Lampiran tidak diizinkan pada catatan internal dalam MVP agar kuota file tidak menjadi kanal untuk mendeteksi aktivitas privat.

## Lampiran

- Disk default: `local` (`storage/app/private`).
- Maksimal 3 file per pengiriman.
- Maksimal 5 MB per file.
- Maksimal 10 file dan 20 MB per tiket.
- Format: PDF, JPG/JPEG, PNG, WEBP, dan TXT.
- Extension dan MIME server-side harus merupakan pasangan yang sah.
- Nama path storage diacak; nama asli hanya menjadi metadata tampilan yang disanitasi.
- File hanya tersedia melalui endpoint download terotorisasi dan tidak mempunyai public URL.
- Retensi MVP mengikuti umur tiket; tidak ada endpoint hapus tiket/lampiran.

Konfigurasi berada di `config/product_requests.php`. Disk dapat diubah melalui `PRODUCT_REQUEST_DISK`, selama disk tersebut privat dan tersedia pada seluruh instance aplikasi yang melayani download.

## Notifikasi

Kanal MVP adalah database notification. Payload hanya berisi jenis event, nomor tiket, status, label status, dan URL internal. Judul, deskripsi, body pesan, nama/path lampiran, serta isi catatan internal tidak disimpan dalam payload notifikasi.

- Request baru → seluruh system owner.
- Balasan/perubahan status tenant → assignee atau seluruh owner bila belum ada assignee.
- Balasan/perubahan status owner → pengaju dan peserta publik dalam koperasi yang sama.
- Catatan internal → owner terkait, tidak pernah tenant.

Pengiriman berjalan setelah commit database. Kegagalan notifikasi dicatat secara tersanitasi dan tidak membatalkan request atau balasan.

## Deployment

1. Jalankan backup sesuai prosedur environment.
2. Jalankan `php artisan migrate --force`.
3. Jalankan `php artisan db:seed --class=PermissionSeeder --force` bila workflow deployment memang menyinkronkan katalog melalui seeder. Migration fitur sudah membuat empat permission baru dan memberikannya kepada seluruh role `admin_primer` yang sudah ada.
4. Jalankan `php artisan optimize:clear` lalu cache ulang sesuai prosedur deployment.
5. Pastikan queue/database, disk private, serta tabel `notifications` dapat ditulis.

Tidak ada akun owner demo yang dibuat. Provisioning owner tetap melalui `php artisan system-owner:provision` dengan password hidden prompt.

## Pemeriksaan keamanan minimum

- Super admin dan actor tanpa permission mendapat 403 pada area request tenant.
- Route owner hanya menerima `system_owner` dan diaudit oleh middleware owner.
- Query tenant selalu memeriksa koperasi serta aturan admin-primer/pemilik.
- Message dan attachment selalu diambil melalui request induk yang telah diotorisasi.
- Sumber duplikat dibatasi pada koperasi yang sama.
- History tidak mempunyai endpoint update/delete dan model menolak mutasi.
- Audit owner tidak menyimpan pencarian, body, alasan, atau metadata file.
