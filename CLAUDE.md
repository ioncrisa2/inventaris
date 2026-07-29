# Design System & Navigasi — Standar Proyek

Berlaku untuk **halaman/komponen baru** dan **revisi yang diminta** ke depannya.
Halaman lama yang belum mengikuti ini TIDAK perlu diretrofit otomatis — cukup
disesuaikan saat memang sedang direvisi/disentuh untuk alasan lain.

Tujuan: hindari "AI slop" / tampilan generik — keterbacaan data dan hierarki
visual yang bersih di atas dekorasi.

## 1. Aturan UI/UX

**Jangan bungkus semua konten dengan card.** Card (border/shadow) cuma untuk
stat widget (angka metrik utama, mis. `<x-stat-card>`). Area filter dan tabel
data **tidak** dibungkus card — biarkan menyatu dengan background halaman
supaya tidak terasa sempit/terkotak-kotak.
- `<x-filter-card>` (card pembungkus filter) **jangan dipakai lagi** di
  halaman baru — ganti dengan panel filter collapsible (lihat poin 4).
- `<x-data-table-card>` yang membungkus tabel dengan card pun sebaiknya
  dihindari untuk halaman baru; render tabel langsung di atas background,
  pakai divider tipis antar baris (`border-bottom` tipis), bukan card.

**Tabel bersih.** Tabel menyatu dengan background utama, pembatas horizontal
tipis antar baris (bukan card border tebal). Header tabel:
- **Sticky** saat halaman di-scroll (`position: sticky; top: <tinggi topbar>`
  pada `<thead>` atau `<th>`).
- Background abu-abu sangat muda (mis. `bg-body-tertiary` / variabel custom
  `--table-header-bg` kalau belum ada, buat baru) supaya baris header tegas
  beda dari baris data — bukan cuma bold text tanpa warna latar seperti
  kebanyakan tabel lama di app ini.

**Rekap + Rincian → Tab, bukan tumpuk vertikal.** Kalau satu halaman punya
tabel "Rekapitulasi" (ringkasan per kategori) dan tabel "Rincian" (baris
detail), JANGAN taruh keduanya atas-bawah di satu scroll panjang (pola lama
di `laporan/inventaris.blade.php` dkk.). Pisah jadi dua tab. Belum ada
komponen tab reusable di app ini — kalau ini halaman pertama yang butuh,
buat `resources/views/components/tabs.blade.php` (Bootstrap nav-tabs biasa,
bukan library baru) lalu reuse untuk halaman berikutnya.

**Filter yang bisa diciutkan (collapsible).** Panel filter laporan (tanggal,
unit kerja, golongan, dst.) default bisa di-collapse. Saat filter aktif
diterapkan, tampilkan cuma ringkasan teks parameter aktif (mis. "Juli 2026 ·
Unit IT · Golongan Kel. 1") di posisi header filter, bukan form penuh
terbuka terus — supaya ruang baca tabel maksimal. Belum ada komponen ini;
kalau membuat, taruh di `resources/views/components/filter-panel.blade.php`
(collapsible pakai Bootstrap `collapse`, bukan `<x-filter-card>` yang lama).

## 2. Struktur Menu Sidebar (Domain-Driven)

Sumber kebenaran navigasi: `app/Support/NavigationMenu.php` (dipakai bareng
sidebar & topbar). JANGAN kumpulkan semua "Laporan" jadi satu grup terpisah
— gabungkan laporan ke grup domain kerjanya masing-masing. Struktur saat ini
(per revisi ini) — kalau menambah menu baru, masukkan ke grup domain yang
paling sesuai, jangan bikin grup "Laporan" baru:

- **Menu Utama**: Dashboard
- **SDM & Kehadiran** (`sdm-kehadiran`): Unit Kerja, Karyawan, Absensi, Hari
  Libur, Laporan Kepegawaian, Laporan Absensi
- **Penggajian** (`penggajian`): Komponen Gaji, Transaksi Gaji, Laporan
  Penggajian
- **Manajemen Aset** (`manajemen-aset`): Inventaris Barang, Penyusutan Aset
  (route `laporan.penyusutan`, label sidebar "Penyusutan Aset" bukan
  "Laporan Penyusutan"), Laporan Inventaris
- **Administrasi** (`administrasi`): Manajemen Pengguna, Role & Hak Akses,
  Pengaturan Aplikasi

Penamaan label sidebar: kalau item itu sendirian atau utama di domainnya,
boleh singkat tanpa prefiks "Laporan" (mis. "Penyusutan Aset"). Kalau
berdampingan dengan data master domain yang sama (mis. Karyawan + Laporan
Kepegawaian), boleh tetap pakai prefiks "Laporan X" supaya jelas beda dari
halaman data masternya.
