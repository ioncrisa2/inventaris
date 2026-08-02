<?php

namespace App\Http\Requests\TransaksiGaji;

use App\Models\KomponenGaji;
use App\Rules\Decimal15Two;
use App\Support\TenantRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
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
                TenantRule::exists('karyawan'),
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
            // 'accepted' itu implicit rule (tetap dievaluasi walau field-nya
            // sama sekali tidak ada di input) — dikombinasikan dengan
            // 'nullable' tetap gagal untuk baris master yang checkbox-nya
            // TIDAK dicentang tapi field tanggal/jumlah_hari-nya tetap ikut
            // terkirim kosong (selalu dirender di _baris.blade.php terlepas
            // status checkbox). 'in:1' bukan implicit rule, jadi baru
            // dievaluasi kalau memang ada isinya — perilaku yang benar-benar
            // "opsional" yang dimaksud oleh 'nullable' di sini.
            'baris.*.pakai' => ['nullable', 'in:1'],
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

                $komponenMasterById = KomponenGaji::query()
                    ->whereKey(array_values(array_unique($masterIds)))
                    ->get()
                    ->keyBy(fn (KomponenGaji $komponen) => (int) $komponen->id);

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
                        $komponenMaster = $komponenMasterById->get((int) $matches[1]);

                        if (! $komponenMaster) {
                            $validator->errors()->add("baris.{$kunci}", 'Komponen gaji yang dipilih sudah tidak tersedia.');

                            continue;
                        }

                        if ($komponenMaster->metode_perhitungan === 'per_hari'
                            && ! $this->validasiRentangTanggal($validator, $kunci, $row)) {
                            continue;
                        }

                        if ($komponenMaster->metode_perhitungan === 'harian_sehari'
                            && ! $this->validasiTanggalTunggal($validator, $kunci, $row)) {
                            continue;
                        }

                        if ($komponenMaster->metode_perhitungan === 'harian_manual'
                            && ! $this->validasiJumlahHariManual($validator, $kunci, $row)) {
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

                    if ($metode === 'per_hari') {
                        $this->validasiRentangTanggal($validator, $kunci, $row);
                    } elseif ($metode === 'harian_sehari') {
                        $this->validasiTanggalTunggal($validator, $kunci, $row);
                    } elseif ($metode === 'harian_manual') {
                        $this->validasiJumlahHariManual($validator, $kunci, $row);
                    }

                    if (! $validator->errors()->has("baris.{$kunci}.metode_perhitungan")
                        && ! $validator->errors()->has("baris.{$kunci}.nilai")
                        && ! $validator->errors()->has("baris.{$kunci}.tanggal_awal")
                        && ! $validator->errors()->has("baris.{$kunci}.tanggal_akhir")
                        && ! $validator->errors()->has("baris.{$kunci}.tanggal")
                        && ! $validator->errors()->has("baris.{$kunci}.jumlah_hari")) {
                        $adaBarisSah = true;
                    }
                }

                if (! $adaBarisSah) {
                    $validator->errors()->add('baris', 'Pilih minimal satu komponen gaji yang masih valid untuk transaksi ini.');
                }
            },
        ];
    }

    /**
     * Validasi rentang tanggal (dari-sampai) untuk baris dengan metode
     * per_hari. Dipakai baik untuk baris master (Tunjangan Uang Makan, dsb.)
     * maupun baris custom, karena rentang tanggal memang input per-transaksi.
     */
    private function validasiRentangTanggal(Validator $validator, string $kunci, array $row): bool
    {
        $subValidator = ValidatorFacade::make($row, [
            'tanggal_awal' => ['required', 'date'],
            'tanggal_akhir' => ['required', 'date', 'after_or_equal:tanggal_awal'],
        ], [
            'tanggal_awal.required' => 'Tanggal awal wajib diisi.',
            'tanggal_awal.date' => 'Tanggal awal tidak valid.',
            'tanggal_akhir.required' => 'Tanggal akhir wajib diisi.',
            'tanggal_akhir.date' => 'Tanggal akhir tidak valid.',
            'tanggal_akhir.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ]);

        if ($subValidator->fails()) {
            foreach ($subValidator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add("baris.{$kunci}.{$field}", $message);
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Validasi tanggal tunggal untuk baris dengan metode harian_sehari.
     * Sama seperti validasiRentangTanggal(), dipakai untuk baris master
     * maupun custom karena tanggalnya memang input per-transaksi.
     */
    private function validasiTanggalTunggal(Validator $validator, string $kunci, array $row): bool
    {
        $subValidator = ValidatorFacade::make($row, [
            'tanggal' => ['required', 'date'],
        ], [
            'tanggal.required' => 'Tanggal wajib diisi.',
            'tanggal.date' => 'Tanggal tidak valid.',
        ]);

        if ($subValidator->fails()) {
            foreach ($subValidator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add("baris.{$kunci}.{$field}", $message);
                }
            }

            return false;
        }

        return true;
    }

    /**
     * Validasi jumlah hari manual untuk baris dengan metode harian_manual.
     */
    private function validasiJumlahHariManual(Validator $validator, string $kunci, array $row): bool
    {
        $subValidator = ValidatorFacade::make($row, [
            'jumlah_hari' => ['required', 'integer', 'min:1', 'max:366'],
        ], [
            'jumlah_hari.required' => 'Jumlah hari wajib diisi.',
            'jumlah_hari.integer' => 'Jumlah hari harus berupa angka bulat.',
            'jumlah_hari.min' => 'Jumlah hari minimal 1.',
            'jumlah_hari.max' => 'Jumlah hari maksimal 366.',
        ]);

        if ($subValidator->fails()) {
            foreach ($subValidator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add("baris.{$kunci}.{$field}", $message);
                }
            }

            return false;
        }

        return true;
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
