<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\DashboardBannerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokumenBarangController;
use App\Http\Controllers\DokumenKaryawanController;
use App\Http\Controllers\FotoBarangController;
use App\Http\Controllers\KaryawanController;
use App\Http\Controllers\KomponenGajiController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RiwayatKondisiBarangController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TransaksiGajiController;
use App\Http\Controllers\UnitKerjaController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Auth::routes(['register' => false, 'reset' => false, 'confirm' => false, 'verify' => false]);

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::patch('/dashboard/banner', DashboardBannerController::class)->name('dashboard.banner.dismiss');
    Route::view('/panduan-singkat', 'panduan-singkat')->name('panduan-singkat');

    Route::delete('unit-kerja/bulk', [UnitKerjaController::class, 'bulkDestroy'])->name('unit-kerja.bulk-destroy');
    Route::resource('unit-kerja', UnitKerjaController::class)->except(['show', 'create', 'edit']);

    Route::delete('karyawan/bulk', [KaryawanController::class, 'bulkDestroy'])->name('karyawan.bulk-destroy');
    Route::patch('karyawan/{karyawan}/status-keaktifan', [KaryawanController::class, 'updateEmploymentStatus'])
        ->name('karyawan.status-keaktifan.update');
    Route::resource('karyawan', KaryawanController::class);

    Route::post('karyawan/{karyawan}/dokumen', [DokumenKaryawanController::class, 'store'])->name('karyawan.dokumen.store');
    Route::get('karyawan/{karyawan}/dokumen/{dokumenKaryawan}/download', [DokumenKaryawanController::class, 'download'])->name('karyawan.dokumen.download');
    Route::delete('karyawan/{karyawan}/dokumen/{dokumenKaryawan}', [DokumenKaryawanController::class, 'destroy'])->name('karyawan.dokumen.destroy');

    Route::prefix('absensi')->name('absensi.')->group(function () {
        Route::get('/', [AbsensiController::class, 'index'])->name('index');
        Route::get('/{karyawan}', [AbsensiController::class, 'show'])->name('show');
        Route::post('/{karyawan}', [AbsensiController::class, 'store'])->name('store');
    });

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

    Route::delete('transaksi-gaji/bulk', [TransaksiGajiController::class, 'bulkDestroy'])->name('transaksi-gaji.bulk-destroy');
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

    Route::delete('pengguna/bulk', [UserController::class, 'bulkDestroy'])->name('pengguna.bulk-destroy');
    Route::resource('pengguna', UserController::class)->except(['show']);

    Route::delete('role/bulk', [RoleController::class, 'bulkDestroy'])->name('role.bulk-destroy');
    Route::resource('role', RoleController::class)->except(['show']);

    Route::get('pengaturan', [PengaturanController::class, 'edit'])->name('pengaturan.edit');
    Route::put('pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.update');

    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('profile.show');

    Route::put('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');
});
