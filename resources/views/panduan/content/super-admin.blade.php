<section class="guide-section" aria-labelledby="super-control-title">
    <div class="guide-section__header">
        <h2 id="super-control-title">Kelola siklus hidup koperasi</h2>
        <p>Fokus utama akun Anda adalah control-plane, bukan entri data operasional harian.</p>
    </div>
    <ol class="quick-guide-list">
        <li>
            <h3>Daftarkan koperasi baru</h3>
            <p>Buka <strong>Administrasi → Manajemen Koperasi → Tambah Koperasi</strong>. Isi nama koperasi, masa aktif, serta akun Admin Primer pertama. Sistem membuat koperasi, role sistem tenant, dan akun pertama dalam satu transaksi.</p>
        </li>
        <li>
            <h3>Kelola masa aktif</h3>
            <p>Gunakan halaman edit koperasi untuk memperpanjang tanggal berlaku atau menonaktifkan akses. Penonaktifan memblokir seluruh akun tenant tanpa menghapus data historisnya.</p>
        </li>
        <li>
            <h3>Periksa konteks sebelum bertindak</h3>
            <p>Label <strong>Seluruh koperasi</strong> menandakan akses lintas tenant. Pada halaman pengguna, role, dan laporan, selalu periksa nama koperasi target sebelum melakukan perubahan atau menarik data.</p>
        </li>
    </ol>
</section>

<section class="guide-section" aria-labelledby="super-access-title">
    <div class="guide-section__header">
        <h2 id="super-access-title">Kelola akses dan konfigurasi bersama</h2>
        <p>Gunakan kontrol global hanya untuk kebutuhan yang memang melintasi tenant.</p>
    </div>
    <ol class="quick-guide-list" start="4">
        <li>
            <h3>Buat role untuk koperasi tertentu</h3>
            <p>Buka <strong>Administrasi → Role & Hak Akses → Tambah Role</strong>. Pilih koperasi tujuan, beri nama role, lalu tetapkan permission. Nama role yang sama boleh digunakan oleh koperasi berbeda karena setiap role disimpan bersama <code>koperasi_id</code>.</p>
        </li>
        <li>
            <h3>Kelola akun pengelola tenant</h3>
            <p>Gunakan <strong>Manajemen Pengguna</strong> untuk meninjau akun berdasarkan koperasi dan role. Penetapan role sistem hanya dilakukan saat benar-benar diperlukan dan harus tetap menyisakan minimal satu Admin Primer aktif pada setiap koperasi.</p>
        </li>
        <li>
            <h3>Sinkronkan hari libur nasional</h3>
            <p>Buka <strong>SDM & Kehadiran → Hari Libur → Sinkronisasi</strong>. Pilih tahun dan tinjau sumber data sebelum menjalankan sinkronisasi untuk seluruh koperasi.</p>
        </li>
    </ol>
</section>

<section class="guide-section" aria-labelledby="super-monitor-title">
    <div class="guide-section__header">
        <h2 id="super-monitor-title">Lakukan pengawasan lintas koperasi</h2>
        <p>Gunakan akses baca global untuk dukungan, audit, dan pemeriksaan konsistensi.</p>
    </div>
    <ol class="quick-guide-list" start="7">
        <li>
            <h3>Tinjau laporan per koperasi</h3>
            <p>Buka laporan Inventaris, Penyusutan, Kepegawaian, Absensi, atau Penggajian. Gunakan filter koperasi dan periode agar hasil yang ditinjau memiliki konteks yang jelas.</p>
        </li>
        <li>
            <h3>Tangani permintaan dukungan dengan aman</h3>
            <p>Konfirmasi koperasi, pengguna, dan objek data sebelum membantu. Hindari perubahan langsung pada data operasional; arahkan pengelola tenant untuk melakukan koreksi melalui alur aplikasi yang tersedia.</p>
        </li>
    </ol>
</section>

<aside class="guide-boundaries" aria-labelledby="super-boundaries-title">
    <h2 id="super-boundaries-title">Batas akses yang perlu diketahui</h2>
    <ul>
        <li>Akses data operasional lintas koperasi bersifat baca untuk pengawasan dan dukungan.</li>
        <li>Mutasi unit kerja, karyawan, barang, absensi, dan transaksi gaji dilakukan oleh pengelola tenant.</li>
        <li>Role sistem dilindungi dan tidak dapat diubah atau dihapus dari halaman Role & Hak Akses.</li>
        <li>Koperasi dinonaktifkan melalui status atau masa aktif, bukan dengan menghapus data tenant.</li>
        <li>Selalu gunakan filter koperasi saat meninjau laporan atau pengguna agar konteks tidak tertukar.</li>
    </ul>
</aside>
