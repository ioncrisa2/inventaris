<?php

namespace App\Repositories;

use App\Models\Absensi;
use App\Models\Karyawan;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class DashboardRepository
{
    /** @return array{total: int, nilai: string} */
    public function ringkasanInventaris(): array
    {
        $ringkasan = DB::table('barang')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(harga_perolehan), 0) AS nilai')
            ->first();

        return [
            'total' => (int) $ringkasan->total,
            'nilai' => (string) $ringkasan->nilai,
        ];
    }

    public function karyawanAktifCount(): int
    {
        return Karyawan::whereNull('tanggal_mengundurkan_diri')->count();
    }

    /**
     * Rekap jumlah status absensi per tanggal dalam satu periode penggajian.
     *
     * @return array<string, array<string, int>>
     */
    public function absensiHarian(CarbonInterface $mulai, CarbonInterface $selesai): array
    {
        return Absensi::query()
            ->without('karyawan')
            ->where('tanggal', '>=', $mulai->toDateString())
            ->where('tanggal', '<', $selesai->toImmutable()->addDay()->toDateString())
            ->selectRaw('tanggal, status, COUNT(*) as total')
            ->groupBy('tanggal', 'status')
            ->get()
            ->groupBy(fn ($baris) => $baris->tanggal->toDateString())
            ->map(fn ($barisPerTanggal) => $barisPerTanggal
                ->pluck('total', 'status')
                ->map(fn ($total) => (int) $total)
                ->all())
            ->all();
    }

    /**
     * Rekap kondisi terakhir seluruh barang ke kelompok operasional yang ringkas.
     *
     * @return array<string, array{label: string, total: int}>
     */
    public function kondisiInventaris(): array
    {
        return $this->ringkasanKondisiInventaris()['grup'];
    }

    /**
     * @return array{grup: array<string, array{label: string, total: int}>, perluPerbaikan: int}
     */
    public function ringkasanKondisiInventaris(): array
    {
        $grup = collect(config('inventaris.kondisi_grup'))
            ->map(fn (array $konfigurasi) => [
                'label' => $konfigurasi['label'],
                'total' => 0,
            ])
            ->all();

        $jumlahPerKondisi = $this->jumlahPerKondisiTerakhir();

        foreach ($jumlahPerKondisi as $kondisi => $total) {
            $kunci = 'belum-diperiksa';

            if ($kondisi !== '') {
                foreach (config('inventaris.kondisi_grup') as $namaGrup => $konfigurasi) {
                    if (in_array($kondisi, $konfigurasi['values'], true)) {
                        $kunci = $namaGrup;
                        break;
                    }
                }
            }

            $grup[$kunci]['total'] += $total;
        }

        return [
            'grup' => $grup,
            'perluPerbaikan' => (int) (($jumlahPerKondisi['Rusak Ringan'] ?? 0) + ($jumlahPerKondisi['Rusak Berat'] ?? 0)),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function dataBelumLengkap(): array
    {
        $barang = DB::table('barang')
            ->selectRaw('SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM riwayat_kondisi_barang WHERE riwayat_kondisi_barang.barang_id = barang.id) THEN 1 ELSE 0 END) AS belum_diperiksa')
            ->selectRaw("SUM(CASE WHEN foto_sampul IS NULL OR foto_sampul = '' THEN 1 ELSE 0 END) AS tanpa_foto")
            ->selectRaw('SUM(CASE WHEN NOT EXISTS (SELECT 1 FROM dokumen_barang WHERE dokumen_barang.barang_id = barang.id AND dokumen_barang.jenis_dokumen = ?) THEN 1 ELSE 0 END) AS tanpa_nota', ['Nota Pembelian'])
            ->first();

        $karyawanTidakLengkap = DB::table('karyawan')
            ->whereNull('tanggal_mengundurkan_diri')
            ->where(function ($query) {
                $query->whereNull('tanggal_masuk_kerja')
                    ->orWhereNull('foto_karyawan')
                    ->orWhere('foto_karyawan', '')
                    ->orWhereNull('nomor_ktp')
                    ->orWhere('nomor_ktp', '')
                    ->orWhereNotExists(function ($dokumen) {
                        $dokumen->selectRaw('1')
                            ->from('dokumen_karyawan')
                            ->whereColumn('dokumen_karyawan.karyawan_id', 'karyawan.id')
                            ->where('jenis_dokumen', 'KTP');
                    });
            })
            ->count();

        return [
            'barangBelumDiperiksa' => (int) ($barang->belum_diperiksa ?? 0),
            'barangTanpaFoto' => (int) ($barang->tanpa_foto ?? 0),
            'barangTanpaNota' => (int) ($barang->tanpa_nota ?? 0),
            'karyawanTidakLengkap' => $karyawanTidakLengkap,
        ];
    }

    /** @return array<string, int> */
    private function jumlahPerKondisiTerakhir(): array
    {
        $tanggalTerakhir = DB::table('riwayat_kondisi_barang')
            ->select('barang_id')
            ->selectRaw('MAX(tanggal_pemeriksaan) AS tanggal_terakhir')
            ->groupBy('barang_id');

        $barisTerakhir = DB::table('riwayat_kondisi_barang AS riwayat')
            ->joinSub($tanggalTerakhir, 'tanggal_terakhir', function ($join) {
                $join->on('tanggal_terakhir.barang_id', '=', 'riwayat.barang_id')
                    ->on('tanggal_terakhir.tanggal_terakhir', '=', 'riwayat.tanggal_pemeriksaan');
            })
            ->select('riwayat.barang_id')
            ->selectRaw('MAX(riwayat.id) AS riwayat_id')
            ->groupBy('riwayat.barang_id');

        return DB::table('barang')
            ->leftJoinSub($barisTerakhir, 'baris_terakhir', 'baris_terakhir.barang_id', '=', 'barang.id')
            ->leftJoin('riwayat_kondisi_barang AS kondisi_terakhir', 'kondisi_terakhir.id', '=', 'baris_terakhir.riwayat_id')
            ->select('kondisi_terakhir.kondisi')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('kondisi_terakhir.kondisi')
            ->get()
            ->mapWithKeys(fn ($baris) => [(string) ($baris->kondisi ?? '') => (int) $baris->total])
            ->all();
    }
}
