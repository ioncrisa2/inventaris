<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class TanggalMerahClient
{
    /**
     * @return list<array{tanggal: string, keterangan: string, jenis: 'holiday'|'leave'}>
     */
    public function holidays(int $tahun): array
    {
        try {
            $response = Http::baseUrl(rtrim((string) config('services.tanggal_merah.base_url'), '/'))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->retry(
                    3,
                    250,
                    fn (\Throwable $exception) => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError()),
                    false,
                )
                ->get('/api/holidays', ['year' => $tahun]);
        } catch (\Throwable) {
            throw new \RuntimeException('Layanan data hari libur publik tidak dapat dihubungi. Coba lagi beberapa saat atau gunakan import Excel.');
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Data hari libur untuk tahun tersebut tidak tersedia dari layanan publik.');
        }

        $payload = $response->json();

        if (! is_array($payload)
            || ($payload['success'] ?? null) !== true
            || ! is_array($payload['data'] ?? null)
            || ! array_is_list($payload['data'])
            || ! is_array($payload['meta'] ?? null)
            || ($payload['meta']['year'] ?? null) !== $tahun) {
            $this->responsTidakValid();
        }

        $meta = $payload['meta'];
        $data = $payload['data'];

        if (($meta['total'] ?? null) !== count($data)
            || ! is_int($meta['total_holidays'] ?? null)
            || ! is_int($meta['total_leave'] ?? null)
            || count($data) !== $meta['total_holidays'] + $meta['total_leave']) {
            $this->responsTidakValid();
        }

        $hasil = [];
        $tanggalDitemukan = [];
        $jumlahPerJenis = ['holiday' => 0, 'leave' => 0];

        foreach ($data as $item) {
            if (! is_array($item)) {
                $this->responsTidakValid();
            }

            $tanggal = $item['date'] ?? null;
            $keterangan = $item['name'] ?? null;
            $jenis = $item['type'] ?? null;
            $tanggalTerurai = is_string($tanggal)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $tanggal)
                : false;

            if (! $tanggalTerurai
                || $tanggalTerurai->format('Y-m-d') !== $tanggal
                || (int) $tanggalTerurai->format('Y') !== $tahun
                || ! is_string($keterangan)
                || trim($keterangan) === ''
                || mb_strlen($keterangan) > 255
                || ! in_array($jenis, ['holiday', 'leave'], true)
                || isset($tanggalDitemukan[$tanggal])) {
                $this->responsTidakValid();
            }

            $tanggalDitemukan[$tanggal] = true;
            $jumlahPerJenis[$jenis]++;
            $hasil[] = [
                'tanggal' => $tanggal,
                'keterangan' => trim($keterangan),
                'jenis' => $jenis,
            ];
        }

        if ($jumlahPerJenis['holiday'] !== $meta['total_holidays']
            || $jumlahPerJenis['leave'] !== $meta['total_leave']) {
            $this->responsTidakValid();
        }

        return $hasil;
    }

    private function responsTidakValid(): never
    {
        throw new \RuntimeException('Respons layanan hari libur tidak valid. Sinkronisasi dibatalkan agar data aplikasi tetap aman.');
    }
}
