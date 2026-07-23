<?php

namespace App\Http\Requests\TransaksiGaji;

use App\Models\KomponenGaji;
use App\Rules\Decimal15Two;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransaksiGajiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'karyawan_id' => [
                'required',
                'exists:karyawan,id',
                Rule::unique('transaksi_gaji', 'karyawan_id')
                    ->where(fn ($query) => $query
                        ->where('bulan', $this->input('bulan'))
                        ->where('tahun', $this->input('tahun')))
                    ->ignore($this->transaksiGajiId()),
            ],
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'digits:4'],
            'baris' => ['required', 'array', 'min:1'],
            'baris.*' => ['array'],
            'baris.*.pakai' => ['nullable', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'baris.required' => 'Pilih minimal satu komponen gaji untuk transaksi ini.',
        ];
    }

    /**
     * Validasi array baris komponen ("master_{id}" atau "custom_{detailId}"
     * untuk baris yatim yang komponen master-nya sudah dihapus).
     *
     * Baris master mengunci metode & nilai ke Komponen Gaji saat ini (tidak
     * bisa diubah per transaksi, lihat TransaksiGajiService::siapkanBaris()),
     * jadi metode_perhitungan/nilai kiriman client untuk baris master tidak
     * divalidasi di sini — hanya baris "custom_*" (yatim, tanpa master) yang
     * memang butuh input metode/nilai manual.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $baris = $this->input('baris', []);
                $adaBarisSah = false;

                if (! is_array($baris)) {
                    return;
                }

                $masterIds = [];
                $customIds = [];

                foreach ($baris as $kunci => $row) {
                    if (! is_array($row) || empty($row['pakai'])) {
                        continue;
                    }

                    if (preg_match('/\Amaster_([1-9]\d*)\z/', (string) $kunci, $matches)) {
                        $masterIds[] = (int) $matches[1];
                    } elseif (preg_match('/\Acustom_([1-9]\d*)\z/', (string) $kunci, $matches)) {
                        $customIds[] = (int) $matches[1];
                    }
                }

                $masterIdsValid = KomponenGaji::query()
                    ->whereKey(array_values(array_unique($masterIds)))
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->flip();

                $transaksiGaji = $this->route('transaksi_gaji');
                $customIdsValid = $transaksiGaji
                    ? $transaksiGaji->details()
                        ->whereKey(array_values(array_unique($customIds)))
                        ->whereNull('komponen_gaji_id')
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->flip()
                    : collect();

                foreach ($baris as $kunci => $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    if (empty($row['pakai'])) {
                        continue;
                    }

                    $kunci = (string) $kunci;

                    if (preg_match('/\Amaster_([1-9]\d*)\z/', $kunci, $matches)) {
                        if (! $masterIdsValid->has((int) $matches[1])) {
                            $validator->errors()->add("baris.{$kunci}", 'Komponen gaji yang dipilih sudah tidak tersedia.');

                            continue;
                        }

                        $adaBarisSah = true;

                        continue;
                    }

                    if (! preg_match('/\Acustom_([1-9]\d*)\z/', $kunci, $matches)) {
                        $validator->errors()->add("baris.{$kunci}", 'Format komponen gaji tidak valid.');

                        continue;
                    }

                    if (! $customIdsValid->has((int) $matches[1])) {
                        $validator->errors()->add("baris.{$kunci}", 'Komponen khusus hanya dapat digunakan dari detail lama transaksi ini.');

                        continue;
                    }

                    $metode = $row['metode_perhitungan'] ?? null;
                    $nilai = $row['nilai'] ?? null;
                    $nilaiTeks = Decimal15Two::normalizeNonNegative($nilai);

                    if (! in_array($metode, array_keys(KomponenGaji::METODE_PERHITUNGAN), true)) {
                        $validator->errors()->add("baris.{$kunci}.metode_perhitungan", 'Metode perhitungan tidak valid.');
                    }

                    if ($nilaiTeks === null) {
                        $validator->errors()->add("baris.{$kunci}.nilai", 'Nilai harus berupa desimal non-negatif, maksimal 13 digit dan 2 angka pecahan.');
                    } elseif ($metode === 'persentase' && bccomp($nilaiTeks, '100', 2) > 0) {
                        $validator->errors()->add("baris.{$kunci}.nilai", 'Nilai persentase harus berada antara 0 sampai 100.');
                    }

                    if (! $validator->errors()->has("baris.{$kunci}.metode_perhitungan")
                        && ! $validator->errors()->has("baris.{$kunci}.nilai")) {
                        $adaBarisSah = true;
                    }
                }

                if (! $adaBarisSah) {
                    $validator->errors()->add('baris', 'Pilih minimal satu komponen gaji yang masih valid untuk transaksi ini.');
                }
            },
        ];
    }

    protected function transaksiGajiId(): ?int
    {
        return null;
    }

    /**
     * @return array{karyawan_id: int, bulan: int, tahun: int}
     */
    public function dataHeader(): array
    {
        return $this->safe()->only(['karyawan_id', 'bulan', 'tahun']);
    }

    /**
     * Hanya baris yang dicentang "pakai" yang disimpan.
     */
    public function barisTerpilih(): array
    {
        return collect($this->input('baris', []))
            ->filter(fn ($row) => ! empty($row['pakai']))
            ->all();
    }
}
