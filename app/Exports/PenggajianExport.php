<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PenggajianExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Builder $transaksiGaji) {}

    public function query(): Builder
    {
        return clone $this->transaksiGaji;
    }

    /** @deprecated Hanya untuk kompatibilitas pemeriksaan data; export memakai query chunked. */
    public function collection(): Collection
    {
        return $this->query()->get();
    }

    public function headings(): array
    {
        return ['Karyawan', 'Unit Kerja', 'Bulan', 'Tahun', 'Gaji Pokok', 'Gaji Bersih'];
    }

    public function map($transaksi): array
    {
        return [
            $transaksi->karyawan?->nama_lengkap ?? '-',
            $transaksi->karyawan?->unitKerja?->nama_unit ?? '-',
            $transaksi->bulan,
            $transaksi->tahun,
            (float) $transaksi->gaji_pokok,
            (float) $transaksi->gaji_bersih,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return ['1' => ['font' => ['bold' => true]]];
    }
}
