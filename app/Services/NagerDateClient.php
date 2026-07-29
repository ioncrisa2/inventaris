<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class NagerDateClient
{
    /**
     * Ambil daftar hari libur nasional Indonesia untuk satu tahun dari API
     * publik Nager.Date (https://date.nager.at), tanpa API key.
     *
     * @return list<array{tanggal: string, keterangan: string}>
     */
    public function publicHolidays(int $tahun): array
    {
        $response = Http::timeout(10)->get(
            config('services.nager_date.base_url')."/publicholidays/{$tahun}/ID"
        );

        if ($response->failed()) {
            throw new \RuntimeException('Gagal mengambil data hari libur dari API.');
        }

        return collect($response->json())
            ->map(fn (array $item) => [
                'tanggal' => $item['date'],
                'keterangan' => $item['localName'],
            ])
            ->all();
    }
}
