<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\DashboardBannerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokumenBarangController;
use App\Http\Controllers\DokumenKaryawanController;
use App\Http\Controllers\FotoBarangController;
use App\Http\Controllers\HariLiburController;
use App\Http\Controllers\HariLiburSinkronisasiController;
use App\Http\Controllers\KaryawanAccountController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\KomponenGajiController;
use App\Http\Controllers\KoperasiController;
use App\Http\Controllers\KoperasiExpiredController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MyAttendanceController;
use App\Http\Controllers\MyProfileController;
use App\Http\Controllers\MySalarySlipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingTourController;
use App\Http\Controllers\Owner\AnnouncementController as OwnerAnnouncementController;
use App\Http\Controllers\Owner\MaintenanceController;
use App\Http\Controllers\Owner\PlatformFeatureController;
use App\Http\Controllers\OwnerAnalyticsController;
use App\Http\Controllers\OwnerProductRequestAttachmentController;
use App\Http\Controllers\OwnerProductRequestController;
use App\Http\Controllers\OwnerProductRequestMessageController;
use App\Http\Controllers\PanduanSingkatController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\ProductRequestAttachmentController;
use App\Http\Controllers\ProductRequestController;
use App\Http\Controllers\ProductRequestMessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RiwayatKaryawanController;
use App\Http\Controllers\RiwayatKondisiBarangController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SlipGajiTemplateController;
use App\Http\Controllers\StorageUsageController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\SystemOwnerDashboardController;
use App\Http\Controllers\TransaksiGajiController;
use App\Http\Controllers\UnitKerjaController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\AuditSystemOwnerAccess;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Auth::routes(['register' => false, 'reset' => false, 'confirm' => false, 'verify' => false]);

Route::middleware('auth')->group(function () {
    Route::get('koperasi/masa-aktif-berakhir', KoperasiExpiredController::class)->name('koperasi.expired');
});

Route::middleware(['auth', 'system_owner', AuditSystemOwnerAccess::class])
    ->prefix('owner')
    ->name('owner.')
    ->group(function () {
        Route::get('/', SystemOwnerDashboardController::class)->name('dashboard');
        Route::get('maintenance', [MaintenanceController::class, 'edit'])->name('maintenance.edit');
        Route::put('maintenance', [MaintenanceController::class, 'update'])->name('maintenance.update');
        Route::delete('maintenance', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy');
        Route::get('features', [PlatformFeatureController::class, 'index'])->name('features.index');
        Route::patch('features/{feature}', [PlatformFeatureController::class, 'update'])
            ->where('feature', '[a-z_]+')
            ->name('features.update');
        Route::get('announcements', [OwnerAnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements', [OwnerAnnouncementController::class, 'store'])->name('announcements.store');
        Route::patch('announcements/{announcement}/publish', [OwnerAnnouncementController::class, 'publish'])->name('announcements.publish');
        Route::get('analytics', [OwnerAnalyticsController::class, 'index'])->name('analytics');
        Route::get('analytics/koperasi/{koperasi}', [OwnerAnalyticsController::class, 'koperasi'])
            ->whereNumber('koperasi')
            ->name('analytics.koperasi');
        Route::middleware('throttle:20,1')->group(function () {
            Route::get('system-health', SystemHealthController::class)->name('system-health');
            Route::get('storage', StorageUsageController::class)->name('storage');
        });

        Route::prefix('product-requests')->name('product-requests.')->group(function () {
            Route::get('/', [OwnerProductRequestController::class, 'index'])->name('index');
            Route::get('{productRequest}', [OwnerProductRequestController::class, 'show'])
                ->where('productRequest', '[A-Z0-9-]+')
                ->name('show');
            Route::get('{productRequest}/attachments/{attachment}', OwnerProductRequestAttachmentController::class)
                ->where('productRequest', '[A-Z0-9-]+')
                ->whereNumber('attachment')
                ->name('attachments.download');
            Route::middleware('throttle:30,1')->group(function () {
                Route::post('{productRequest}/messages', [OwnerProductRequestMessageController::class, 'store'])
                    ->where('productRequest', '[A-Z0-9-]+')
                    ->name('messages.store');
                Route::patch('{productRequest}/triage', [OwnerProductRequestController::class, 'update'])
                    ->where('productRequest', '[A-Z0-9-]+')
                    ->name('triage.update');
            });
        });
    });

Route::middleware(['auth', 'koperasi.active'])->group(function () {
    Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show');
    Route::prefix('saya')->name('me.')->group(function () {
        Route::get('/', MyProfileController::class)->name('profile');
        Route::get('absensi', MyAttendanceController::class)->name('attendance');
        Route::get('slip-gaji', [MySalarySlipController::class, 'index'])->name('salary-slips.index');
        Route::get('slip-gaji/{transaksiGaji}', [MySalarySlipController::class, 'show'])->name('salary-slips.show');
    });
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::patch('/dashboard/banner', DashboardBannerController::class)->name('dashboard.banner.dismiss');
    Route::patch('/onboarding/tour', OnboardingTourController::class)->name('onboarding.tour.finish');
    Route::get('/panduan-singkat', [PanduanSingkatController::class, 'show'])->name('panduan-singkat');
    Route::get('/panduan-singkat/cetak', [PanduanSingkatController::class, 'print'])->name('panduan-singkat.cetak');

    Route::prefix('product-requests')->name('product-requests.')->group(function () {
        Route::get('/', [ProductRequestController::class, 'index'])->name('index');
        Route::get('create', [ProductRequestController::class, 'create'])->name('create');
        Route::post('/', [ProductRequestController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('store');
        Route::get('{productRequest}', [ProductRequestController::class, 'show'])
            ->where('productRequest', '[A-Z0-9-]+')
            ->name('show');
        Route::get('{productRequest}/attachments/{attachment}', ProductRequestAttachmentController::class)
            ->where('productRequest', '[A-Z0-9-]+')
            ->whereNumber('attachment')
            ->name('attachments.download');
        Route::post('{productRequest}/messages', [ProductRequestMessageController::class, 'store'])
            ->middleware('throttle:30,1')
            ->where('productRequest', '[A-Z0-9-]+')
            ->name('messages.store');
        Route::patch('{productRequest}/state', [ProductRequestController::class, 'toggle'])
            ->middleware('throttle:20,1')
            ->where('productRequest', '[A-Z0-9-]+')
            ->name('state.toggle');
    });

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}', [NotificationController::class, 'open'])
        ->whereUuid('notification')
        ->name('notifications.open');
    Route::patch('notifications', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::delete('unit-kerja/bulk', [UnitKerjaController::class, 'bulkDestroy'])->name('unit-kerja.bulk-destroy');
    Route::resource('unit-kerja', UnitKerjaController::class)->except(['show', 'create', 'edit']);

    Route::delete('karyawan/bulk', [KaryawanController::class, 'bulkDestroy'])->name('karyawan.bulk-destroy');
    Route::put('karyawan/{karyawan}/akun', [KaryawanAccountController::class, 'update'])->name('karyawan.akun.update');
    Route::delete('karyawan/{karyawan}/akun', [KaryawanAccountController::class, 'destroy'])->name('karyawan.akun.destroy');
    Route::resource('karyawan', KaryawanController::class)->except(['edit', 'update']);

    Route::post('karyawan/{karyawan}/riwayat', [RiwayatKaryawanController::class, 'store'])
        ->name('karyawan.riwayat.store');
    Route::get(
        'karyawan/{karyawan}/riwayat/{riwayatKaryawan}/dokumen/{dokumenRiwayatKaryawan}',
        [RiwayatKaryawanController::class, 'download'],
    )->name('karyawan.riwayat.dokumen.download');

    Route::get('karyawan/{karyawan}/dokumen/{dokumenKaryawan}/download', [DokumenKaryawanController::class, 'download'])->name('karyawan.dokumen.download');

    Route::prefix('absensi')->name('absensi.')->group(function () {
        Route::get('/', [AbsensiController::class, 'index'])->name('index');
        Route::get('/{karyawan}', [AbsensiController::class, 'show'])->name('show');
        Route::post('/{karyawan}', [AbsensiController::class, 'store'])->name('store');
    });

    Route::middleware(['super_admin', 'throttle:10,1'])->group(function () {
        Route::get('hari-libur/sinkronisasi', [HariLiburSinkronisasiController::class, 'create'])
            ->name('hari-libur.sinkronisasi.create');
        Route::post('hari-libur/sinkronisasi', [HariLiburSinkronisasiController::class, 'store'])
            ->name('hari-libur.sinkronisasi.store');
    });

    Route::delete('hari-libur/bulk', [HariLiburController::class, 'bulkDestroy'])->name('hari-libur.bulk-destroy');
    Route::get('hari-libur/template', [HariLiburController::class, 'template'])->name('hari-libur.template');
    Route::post('hari-libur/import', [HariLiburController::class, 'import'])->name('hari-libur.import');
    Route::get('hari-libur/{tahun}/koperasi/{koperasi}', [HariLiburController::class, 'koperasi'])
        ->middleware('super_admin')
        ->whereNumber(['tahun', 'koperasi'])
        ->name('hari-libur.koperasi');
    Route::get('hari-libur/{tahun}', [HariLiburController::class, 'tahun'])->whereNumber('tahun')->name('hari-libur.tahun');
    Route::resource('hari-libur', HariLiburController::class)->except(['show', 'create', 'edit']);

    Route::post('barang/barcode/bulk', [BarangController::class, 'barcodeMassal'])->name('barang.barcode.bulk');
    Route::delete('barang/bulk', [BarangController::class, 'bulkDestroy'])->name('barang.bulk-destroy');
    Route::get('barang/{barang}/barcode', [BarangController::class, 'barcode'])->name('barang.barcode');
    Route::get('barang/{barang}/qr-code', [BarangController::class, 'qrCode'])->name('barang.qr-code');

    Route::resource('barang', BarangController::class);

    Route::post('barang/{barang}/kondisi', [RiwayatKondisiBarangController::class, 'store'])
        ->name('barang.kondisi.store');

    Route::post('barang/{barang}/foto', [FotoBarangController::class, 'store'])->name('barang.foto.store');
    Route::delete('barang/{barang}/foto/{fotoBarang}', [FotoBarangController::class, 'destroy'])->name('barang.foto.destroy');

    Route::post('barang/{barang}/dokumen', [DokumenBarangController::class, 'store'])->name('barang.dokumen.store');
    Route::get('barang/{barang}/dokumen/{dokumenBarang}/download', [DokumenBarangController::class, 'download'])->name('barang.dokumen.download');
    Route::delete('barang/{barang}/dokumen/{dokumenBarang}', [DokumenBarangController::class, 'destroy'])->name('barang.dokumen.destroy');

    Route::delete('komponen-gaji/bulk', [KomponenGajiController::class, 'bulkDestroy'])->name('komponen-gaji.bulk-destroy');
    Route::resource('komponen-gaji', KomponenGajiController::class)->except(['show', 'create', 'edit']);

    Route::get('transaksi-gaji/cetak-massal', [TransaksiGajiController::class, 'cetakMassal'])
        ->name('transaksi-gaji.cetak-massal');
    Route::delete('transaksi-gaji/bulk', [TransaksiGajiController::class, 'bulkDestroy'])->name('transaksi-gaji.bulk-destroy');
    Route::patch('transaksi-gaji/{transaksiGaji}/terbitkan', [TransaksiGajiController::class, 'publish'])->name('transaksi-gaji.publish');
    Route::get('transaksi-gaji/karyawan/{karyawan}', [TransaksiGajiController::class, 'karyawan'])->name('transaksi-gaji.karyawan');
    Route::resource('transaksi-gaji', TransaksiGajiController::class);

    Route::get('transaksi-gaji/{transaksiGaji}/cetak', [TransaksiGajiController::class, 'cetak'])
        ->name('transaksi-gaji.cetak');

    Route::get('/laporan/inventaris', [LaporanController::class, 'inventaris'])
        ->name('laporan.inventaris');

    Route::get('/laporan/inventaris/cetak', [LaporanController::class, 'cetakInventaris'])
        ->name('laporan.inventaris.cetak');
    Route::get('/laporan/inventaris/export', [LaporanController::class, 'exportInventaris'])
        ->name('laporan.inventaris.export');

    Route::get('/laporan/absensi', [LaporanController::class, 'absensi'])
        ->name('laporan.absensi');
    Route::get('/laporan/absensi/cetak', [LaporanController::class, 'cetakAbsensi'])
        ->name('laporan.absensi.cetak');
    Route::get('/laporan/absensi/export', [LaporanController::class, 'exportAbsensi'])
        ->name('laporan.absensi.export');

    Route::get('/laporan/kepegawaian', [LaporanController::class, 'kepegawaian'])
        ->name('laporan.kepegawaian');
    Route::get('/laporan/kepegawaian/cetak', [LaporanController::class, 'cetakKepegawaian'])
        ->name('laporan.kepegawaian.cetak');
    Route::get('/laporan/kepegawaian/export', [LaporanController::class, 'exportKepegawaian'])
        ->name('laporan.kepegawaian.export');

    Route::get('/laporan/penggajian', [LaporanController::class, 'penggajian'])
        ->name('laporan.penggajian');
    Route::get('/laporan/penggajian/cetak', [LaporanController::class, 'cetakPenggajian'])
        ->name('laporan.penggajian.cetak');
    Route::get('/laporan/penggajian/export', [LaporanController::class, 'exportPenggajian'])
        ->name('laporan.penggajian.export');

    Route::get('/laporan/penyusutan', [LaporanController::class, 'penyusutan'])
        ->name('laporan.penyusutan');
    Route::get('/laporan/penyusutan/cetak', [LaporanController::class, 'cetakPenyusutan'])
        ->name('laporan.penyusutan.cetak');
    Route::get('/laporan/penyusutan/export', [LaporanController::class, 'exportPenyusutan'])
        ->name('laporan.penyusutan.export');

    Route::delete('pengguna/bulk', [UserController::class, 'bulkDestroy'])->name('pengguna.bulk-destroy');
    Route::resource('pengguna', UserController::class)->except(['show']);

    Route::delete('role/bulk', [RoleController::class, 'bulkDestroy'])->name('role.bulk-destroy');
    Route::resource('role', RoleController::class)->except(['show']);

    Route::middleware('super_admin')->group(function () {
        Route::resource('koperasi', KoperasiController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    });

    Route::get('pengaturan', [PengaturanController::class, 'edit'])->name('pengaturan.edit');
    Route::get('pengaturan/slip-gaji', [SlipGajiTemplateController::class, 'edit'])->name('pengaturan.slip-gaji.edit');
    Route::post('pengaturan/slip-gaji/draf', [SlipGajiTemplateController::class, 'saveDraft'])->name('pengaturan.slip-gaji.draft');
    Route::post('pengaturan/slip-gaji/terbitkan', [SlipGajiTemplateController::class, 'publish'])->name('pengaturan.slip-gaji.publish');
    Route::put('pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');
    Route::put('pengaturan/hari-operasional', [PengaturanController::class, 'updateHariOperasional'])->name('pengaturan.hari-operasional.update');
    Route::put('pengaturan/identitas', [PengaturanController::class, 'updateIdentitas'])->name('pengaturan.identitas.update');

    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('profile.show');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');
});
