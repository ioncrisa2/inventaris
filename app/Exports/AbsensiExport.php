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

class AbsensiExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private Builder $absensis) {}

    public function query(): Builder
    {
        return clone $this->absensis;
    }

    /** @deprecated Hanya untuk kompatibilitas pemeriksaan data; export memakai query chunked. */
    public function collection(): Collection
    {
        return $this->query()->get();
    }

    public function headings(): array
    {
        return ['Tanggal', 'NIK', 'Nama Karyawan', 'Unit Kerja', 'Status', 'Catatan'];
    }

    public function map($absensi): array
    {
        return [
            $absensi->tanggal->format('Y-m-d'),
            $absensi->karyawan?->nik ?? '-',
            $absensi->karyawan?->nama_lengkap ?? '-',
            $absensi->karyawan?->unitKerja?->nama_unit ?? '-',
            $absensi->status,
            $absensi->catatan ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return ['1' => ['font' => ['bold' => true]]];
    }
}
