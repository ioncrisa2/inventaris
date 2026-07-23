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

class InventarisExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Builder $barangs) {}

    public function query(): Builder
    {
        return clone $this->barangs;
    }

    /** @deprecated Hanya untuk kompatibilitas pemeriksaan data; export memakai query chunked. */
    public function collection(): Collection
    {
        return $this->query()->get();
    }

    public function headings(): array
    {
        return ['Kode Barang', 'Nama Barang', 'Golongan', 'Unit Kerja', 'Tanggal Perolehan', 'Harga Perolehan', 'Kondisi Terakhir'];
    }

    public function map($barang): array
    {
        return [
            $barang->kode_barang,
            $barang->nama_barang,
            $barang->kategori,
            $barang->unitKerja?->nama_unit ?? '-',
            $barang->tanggal_perolehan->format('Y-m-d'),
            (float) $barang->harga_perolehan,
            $barang->kondisiTerakhir?->kondisi ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return ['1' => ['font' => ['bold' => true]]];
    }
}
