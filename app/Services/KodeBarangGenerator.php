<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Repositories\BarangRepository;
use App\Repositories\UnitKerjaRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KodeBarangGenerator
{
    public const DEFAULT_TEMPLATE = '{UNIT}-{KATEGORI}-{TAHUN}-{URUT}';

    public const DEFAULT_SEQUENCE_DIGITS = 4;

    public const MIN_SEQUENCE_DIGITS = 3;

    public const MAX_SEQUENCE_DIGITS = 8;

    /**
     * Token yang bisa dipakai di template format kode barang.
     */
    public const TOKENS = ['{KATEGORI}', '{UNIT}', '{TAHUN}', '{BULAN}', '{URUT}'];

    public function __construct(
        private BarangRepository $barangRepository,
        private UnitKerjaRepository $unitKerjaRepository,
    ) {}

    public function template(): string
    {
        return Pengaturan::get('format_kode_barang', self::DEFAULT_TEMPLATE);
    }

    public function simpanTemplate(string $template): void
    {
        DB::transaction(fn () => Pengaturan::set('format_kode_barang', $template), 3);
    }

    public function sequenceDigits(): int
    {
        return (int) Pengaturan::get('digit_nomor_urut', (string) self::DEFAULT_SEQUENCE_DIGITS);
    }

    public function simpanPengaturan(string $template, int $sequenceDigits): void
    {
        DB::transaction(function () use ($template, $sequenceDigits) {
            Pengaturan::set('format_kode_barang', $template);
            Pengaturan::set('digit_nomor_urut', (string) $sequenceDigits);
        }, 3);
    }

    /**
     * Generate kode_barang unik berdasarkan template yang berlaku. Nomor urut
     * dicoba beberapa kali (bukan sekali hitung) untuk berjaga-jaga terhadap
     * kemungkinan tabrakan (mis. barang lain dibuat di antara hitung & simpan).
     */
    public function generate(string $kategori, int $unitKerjaId, string $tanggalPerolehan): string
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Kode barang wajib dibuat di dalam transaksi database.');
        }

        $pengaturan = $this->pengaturanTerkunci();
        $template = $pengaturan['format_kode_barang'];
        $sequenceDigits = (int) $pengaturan['digit_nomor_urut'];
        $unitKerja = $this->unitKerjaRepository->find($unitKerjaId);
        $tanggal = Carbon::parse($tanggalPerolehan);
        $urut = $this->barangRepository->count() + 1;

        for ($percobaan = 0; $percobaan < 20; $percobaan++) {
            $kode = $this->substitusi($template, [
                '{KATEGORI}' => $this->kodeKategori($kategori),
                '{UNIT}' => $this->kodeUnit($unitKerja?->kode, $unitKerja?->nama_unit),
                '{TAHUN}' => $tanggal->format('Y'),
                '{BULAN}' => $tanggal->format('m'),
                '{URUT}' => str_pad((string) $urut, $sequenceDigits, '0', STR_PAD_LEFT),
            ]);

            if (! $this->barangRepository->kodeExists($kode)) {
                return $kode;
            }

            $urut++;
        }

        throw new \RuntimeException('Gagal membuat kode barang unik, coba lagi.');
    }

    /**
     * Baris pengaturan dipakai sebagai mutex portable. Insert-or-ignore membuat
     * mutex tetap tersedia pada instalasi yang belum pernah menjalankan seeder.
     * Lock dipertahankan hingga BarangService selesai menyisipkan barang.
     *
     * @return array{format_kode_barang: string, digit_nomor_urut: string}
     */
    private function pengaturanTerkunci(): array
    {
        $waktu = now();
        Pengaturan::query()->insertOrIgnore([
            [
                'key' => 'digit_nomor_urut',
                'value' => (string) self::DEFAULT_SEQUENCE_DIGITS,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'key' => 'format_kode_barang',
                'value' => self::DEFAULT_TEMPLATE,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        ]);

        $nilai = Pengaturan::query()
            ->whereIn('key', ['digit_nomor_urut', 'format_kode_barang'])
            ->orderBy('key')
            ->lockForUpdate()
            ->pluck('value', 'key');

        return [
            'format_kode_barang' => (string) $nilai->get('format_kode_barang', self::DEFAULT_TEMPLATE),
            'digit_nomor_urut' => (string) $nilai->get('digit_nomor_urut', self::DEFAULT_SEQUENCE_DIGITS),
        ];
    }

    /**
     * Contoh hasil template (nilai statis), dipakai sebagai pratinjau di
     * halaman Pengaturan supaya admin tahu bentuk kode sebelum menyimpan.
     */
    public function contoh(string $template, ?int $sequenceDigits = null): string
    {
        $sequenceDigits ??= $this->sequenceDigits();

        return $this->substitusi($template, [
            '{KATEGORI}' => 'ELK',
            '{UNIT}' => 'IT',
            '{TAHUN}' => now()->format('Y'),
            '{BULAN}' => now()->format('m'),
            '{URUT}' => str_pad('1', $sequenceDigits, '0', STR_PAD_LEFT),
        ]);
    }

    private function substitusi(string $template, array $nilai): string
    {
        return strtr($template, $nilai);
    }

    private function kodeKategori(string $kategori): string
    {
        return config('inventaris.kategori_kode')[$kategori] ?? Str::upper(Str::substr($kategori, 0, 3));
    }

    private function kodeUnit(?string $kode, ?string $namaUnit): string
    {
        if (filled($kode)) {
            return Str::upper($kode);
        }

        if (filled($namaUnit)) {
            return Str::upper(Str::substr($namaUnit, 0, 3));
        }

        return 'UMU';
    }
}
