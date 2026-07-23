<?php

use App\Exports\AbsensiExport;
use App\Exports\InventarisExport;
use App\Exports\KepegawaianExport;
use App\Exports\PenggajianExport;
use App\Models\Barang;
use App\Models\RiwayatKondisiBarang;
use App\Models\UnitKerja;
use App\Repositories\BarangRepository;
use App\Repositories\LaporanRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Concerns\FromQuery;

uses(RefreshDatabase::class);

function memilikiIndeks(string $table, array $columns): bool
{
    return collect(Schema::getIndexes($table))
        ->contains(fn (array $index) => $index['columns'] === $columns);
}

test('indeks komposit untuk query kritis tersedia', function () {
    expect(memilikiIndeks('absensi', ['tanggal', 'status']))->toBeTrue()
        ->and(memilikiIndeks('transaksi_gaji', ['tahun', 'bulan']))->toBeTrue()
        ->and(memilikiIndeks('riwayat_kondisi_barang', ['barang_id', 'tanggal_pemeriksaan']))->toBeTrue()
        ->and(memilikiIndeks('dokumen_barang', ['barang_id', 'jenis_dokumen']))->toBeTrue()
        ->and(memilikiIndeks('dokumen_karyawan', ['karyawan_id', 'jenis_dokumen']))->toBeTrue()
        ->and(memilikiIndeks('transaksi_gaji_detail', ['transaksi_gaji_id', 'jenis_snapshot']))->toBeTrue()
        ->and(memilikiIndeks('barang', ['tanggal_perolehan']))->toBeTrue();
});

test('filter laporan tanggal menggunakan rentang yang ramah indeks', function () {
    $repository = app(LaporanRepository::class);
    $sqlAbsensi = strtolower($repository->absensiQuery([], 7, 2026)->toSql());
    $sqlInventaris = strtolower($repository->inventarisQuery([
        'tanggal_awal' => '2026-07-01',
        'tanggal_akhir' => '2026-07-31',
    ])->toSql());

    expect($sqlAbsensi)
        ->not->toContain('strftime', 'month(', 'year(', 'date(')
        ->and($sqlInventaris)->not->toContain('date(');
});

test('query daftar barang tidak bertambah mengikuti jumlah baris', function () {
    $unit = UnitKerja::create(['nama_unit' => 'Teknologi', 'kode' => 'IT']);
    $repository = app(BarangRepository::class);

    $hitungQuery = function () use ($repository): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $items = $repository->paginate([], 100)->getCollection();
        $items->each(fn (Barang $barang) => [$barang->unitKerja?->nama_unit, $barang->kondisiTerakhir?->kondisi]);
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    };

    $buatBarang = function (int $nomor) use ($unit): void {
        $barang = Barang::create([
            'kode_barang' => "BRG-Q-{$nomor}",
            'nama_barang' => "Barang {$nomor}",
            'kategori' => 'Bukan Bangunan - Kelompok 1',
            'unit_kerja_id' => $unit->id,
            'tanggal_perolehan' => '2026-07-01',
            'harga_perolehan' => 100000,
        ]);
        RiwayatKondisiBarang::create([
            'barang_id' => $barang->id,
            'tanggal_pemeriksaan' => '2026-07-01',
            'kondisi' => 'Baik',
        ]);
    };

    $buatBarang(1);
    $satuBaris = $hitungQuery();

    foreach (range(2, 25) as $nomor) {
        $buatBarang($nomor);
    }

    expect($hitungQuery())->toBe($satuBaris);
});

test('export laporan menggunakan query chunked', function () {
    foreach ([InventarisExport::class, AbsensiExport::class, KepegawaianExport::class, PenggajianExport::class] as $export) {
        expect(is_subclass_of($export, FromQuery::class))->toBeTrue();
    }
});
