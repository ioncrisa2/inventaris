<?php

namespace App\Providers;

use App\Contracts\VirusScanner;
use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\DokumenKaryawan;
use App\Models\DokumenRiwayatKaryawan;
use App\Models\FotoBarang;
use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\ProductRequestAttachment;
use App\Models\RiwayatKaryawan;
use App\Services\ClamAvScanner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(VirusScanner::class, ClamAvScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'barang' => Barang::class,
            'foto_barang' => FotoBarang::class,
            'dokumen_barang' => DokumenBarang::class,
            'karyawan' => Karyawan::class,
            'dokumen_karyawan' => DokumenKaryawan::class,
            'riwayat_karyawan' => RiwayatKaryawan::class,
            'dokumen_riwayat_karyawan' => DokumenRiwayatKaryawan::class,
            'koperasi' => Koperasi::class,
            'product_request_attachment' => ProductRequestAttachment::class,
        ]);

        Model::preventLazyLoading(! app()->isProduction());
        Paginator::useBootstrapFive();
        Paginator::defaultView('vendor.pagination.app');
    }
}
