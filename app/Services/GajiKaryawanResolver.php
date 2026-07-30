<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\RiwayatKaryawanPerubahan;
use App\Rules\Decimal15Two;
use Carbon\CarbonInterface;

class GajiKaryawanResolver
{
    public function berlakuPada(Karyawan $karyawan, CarbonInterface $tanggal): string
    {
        $perubahanBerlaku = RiwayatKaryawanPerubahan::query()
            ->where('field', 'gaji_pokok')
            ->whereHas('riwayat', fn ($query) => $query
                ->where('karyawan_id', $karyawan->id)
                ->whereDate('tanggal_berlaku', '<=', $tanggal->toDateString()))
            ->join('riwayat_karyawan', 'riwayat_karyawan.id', '=', 'riwayat_karyawan_perubahan.riwayat_karyawan_id')
            ->orderByDesc('riwayat_karyawan.tanggal_berlaku')
            ->orderByDesc('riwayat_karyawan.created_at')
            ->orderByDesc('riwayat_karyawan_perubahan.id')
            ->value('riwayat_karyawan_perubahan.nilai_baru');

        if ($perubahanBerlaku !== null) {
            return $this->normalisasi($perubahanBerlaku);
        }

        $perubahanSetelahnya = RiwayatKaryawanPerubahan::query()
            ->where('field', 'gaji_pokok')
            ->whereHas('riwayat', fn ($query) => $query
                ->where('karyawan_id', $karyawan->id)
                ->whereDate('tanggal_berlaku', '>', $tanggal->toDateString()))
            ->join('riwayat_karyawan', 'riwayat_karyawan.id', '=', 'riwayat_karyawan_perubahan.riwayat_karyawan_id')
            ->orderBy('riwayat_karyawan.tanggal_berlaku')
            ->orderBy('riwayat_karyawan.created_at')
            ->orderBy('riwayat_karyawan_perubahan.id')
            ->value('riwayat_karyawan_perubahan.nilai_lama');

        return $this->normalisasi($perubahanSetelahnya ?? $karyawan->gaji_pokok);
    }

    private function normalisasi(mixed $nilai): string
    {
        $normal = Decimal15Two::normalizeNonNegative($nilai);

        if ($normal === null) {
            throw new \DomainException('Nilai gaji pokok karyawan tidak valid.');
        }

        return bcadd($normal, '0', 2);
    }
}
