<?php

namespace App\Imports;

use App\Models\HariLibur;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class HariLiburImport implements SkipsEmptyRows, SkipsOnFailure, ToCollection, WithHeadingRow, WithValidation
{
    use SkipsFailures;

    public int $jumlahDitambahkan = 0;

    public int $jumlahSudahAda = 0;

    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {
            $tanggal = $this->parseTanggal($row['tanggal'] ?? null);

            if ($tanggal === null) {
                continue;
            }

            $hariLibur = HariLibur::firstOrCreate(
                ['tanggal' => $tanggal],
                ['keterangan' => trim((string) $row['keterangan'])],
            );

            $hariLibur->wasRecentlyCreated ? $this->jumlahDitambahkan++ : $this->jumlahSudahAda++;
        }
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required'],
            'keterangan' => ['required', 'string', 'max:255'],
        ];
    }

    public function customValidationAttributes(): array
    {
        return ['tanggal' => 'Tanggal', 'keterangan' => 'Keterangan'];
    }

    private function parseTanggal(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->format('Y-m-d');
        }

        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
