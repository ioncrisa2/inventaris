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

class KepegawaianExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Builder $karyawans) {}

    public function query(): Builder
    {
        return clone $this->karyawans;
    }

    /** @deprecated Hanya untuk kompatibilitas pemeriksaan data; export memakai query chunked. */
    public function collection(): Collection
    {
        return $this->query()->get();
    }

    public function headings(): array
    {
        return ['NIK', 'Nama Lengkap', 'Unit Kerja', 'Jabatan', 'Status', 'Gaji Pokok'];
    }

    public function map($karyawan): array
    {
        return [
            $karyawan->nik,
            $karyawan->nama_lengkap,
            $karyawan->unitKerja?->nama_unit ?? '-',
            $karyawan->jabatan,
            $karyawan->status_karyawan,
            (float) $karyawan->gaji_pokok,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return ['1' => ['font' => ['bold' => true]]];
    }
}
