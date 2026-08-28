<?php

namespace App\Exports;

use App\Support\PenyusutanCalculator;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Berbeda dari export laporan lain (FromQuery): nilai penyusutan dihitung
 * (bukan kolom database apa adanya), jadi baris yang diekspor sudah berupa
 * data siap pakai (Collection), bukan query builder yang bisa dijalankan
 * ulang per chunk.
 */
class PenyusutanExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'Kode Barang',
            'Nama Barang',
            'Golongan',
            'Metode',
            'Masa Manfaat (Tahun)',
            'Tanggal Perolehan',
            'Harga Perolehan',
            'Akumulasi Awal Tahun',
            'Penyusutan Tahun Ini',
            'Akumulasi Akhir Tahun',
            'Nilai Buku Akhir Tahun',
        ];
    }

    public function map($row): array
    {
        $barang = $row['barang'];

        return [
            $barang->kode_barang,
            $barang->nama_barang,
            $barang->kategori,
            PenyusutanCalculator::namaMetode($row['metode']),
            $row['masa_manfaat_tahun'],
            $barang->tanggal_perolehan->format('Y-m-d'),
            (float) $barang->harga_perolehan,
            (float) $row['akumulasi_awal_tahun'],
            (float) $row['penyusutan_tahun_ini'],
            (float) $row['akumulasi_akhir_tahun'],
            (float) $row['nilai_buku_akhir_tahun'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return ['1' => ['font' => ['bold' => true]]];
    }
}
