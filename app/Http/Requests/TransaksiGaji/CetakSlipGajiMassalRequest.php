<?php

namespace App\Http\Requests\TransaksiGaji;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CetakSlipGajiMassalRequest extends CetakSlipGajiRequest
{
    public function rules(): array
    {
        $modePeriode = $this->input('mode') === 'periode';
        $modeKaryawan = $this->input('mode') === 'karyawan';

        return [
            ...$this->commonRules(),
            'mode' => ['required', Rule::in(['periode', 'karyawan'])],
            'bulan' => [Rule::requiredIf($modePeriode), Rule::prohibitedIf(! $modePeriode), 'integer', 'between:1,12'],
            'tahun' => [Rule::requiredIf($modePeriode), Rule::prohibitedIf(! $modePeriode), 'integer', 'between:2000,2100'],
            'karyawan_id' => [Rule::requiredIf($modeKaryawan), Rule::prohibitedIf(! $modeKaryawan), 'integer', 'exists:karyawan,id'],
            'periode_awal' => [Rule::requiredIf($modeKaryawan), Rule::prohibitedIf(! $modeKaryawan), 'date_format:Y-m'],
            'periode_akhir' => [Rule::requiredIf($modeKaryawan), Rule::prohibitedIf(! $modeKaryawan), 'date_format:Y-m'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('mode') !== 'karyawan'
                    || $validator->errors()->hasAny(['periode_awal', 'periode_akhir'])) {
                    return;
                }

                $periodeAwal = $this->periodIndex($this->input('periode_awal'));
                $periodeAkhir = $this->periodIndex($this->input('periode_akhir'));
                $periodeMinimum = $this->periodIndex('2000-01');
                $periodeMaksimum = $this->periodIndex('2100-12');

                if ($periodeAwal < $periodeMinimum || $periodeAwal > $periodeMaksimum) {
                    $validator->errors()->add('periode_awal', 'Periode awal harus antara Januari 2000 dan Desember 2100.');
                }

                if ($periodeAkhir < $periodeMinimum || $periodeAkhir > $periodeMaksimum) {
                    $validator->errors()->add('periode_akhir', 'Periode akhir harus antara Januari 2000 dan Desember 2100.');
                }

                if ($periodeAkhir < $periodeAwal) {
                    $validator->errors()->add('periode_akhir', 'Periode akhir tidak boleh sebelum periode awal.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'mode.required' => 'Pilih cakupan slip yang akan dicetak.',
            'mode.in' => 'Cakupan cetak slip tidak valid.',
            'bulan.required' => 'Pilih bulan transaksi.',
            'bulan.between' => 'Bulan transaksi tidak valid.',
            'tahun.required' => 'Isi tahun transaksi.',
            'tahun.between' => 'Tahun transaksi harus antara 2000 dan 2100.',
            'karyawan_id.required' => 'Pilih satu karyawan.',
            'karyawan_id.exists' => 'Karyawan yang dipilih tidak tersedia.',
            'periode_awal.required' => 'Pilih periode awal.',
            'periode_awal.date_format' => 'Format periode awal tidak valid.',
            'periode_akhir.required' => 'Pilih periode akhir.',
            'periode_akhir.date_format' => 'Format periode akhir tidak valid.',
            '*.prohibited' => 'Filter yang dikirim tidak sesuai dengan cakupan cetak.',
        ];
    }

    /**
     * @return array<string, int|string>
     */
    public function filter(): array
    {
        $data = $this->validated();

        if ($data['mode'] === 'periode') {
            return [
                'mode' => 'periode',
                'bulan' => (int) $data['bulan'],
                'tahun' => (int) $data['tahun'],
            ];
        }

        [$tahunAwal, $bulanAwal] = $this->periodParts($data['periode_awal']);
        [$tahunAkhir, $bulanAkhir] = $this->periodParts($data['periode_akhir']);

        return [
            'mode' => 'karyawan',
            'karyawan_id' => (int) $data['karyawan_id'],
            'tahun_awal' => $tahunAwal,
            'bulan_awal' => $bulanAwal,
            'tahun_akhir' => $tahunAkhir,
            'bulan_akhir' => $bulanAkhir,
        ];
    }

    private function periodIndex(mixed $period): int
    {
        [$year, $month] = $this->periodParts((string) $period);

        return ($year * 12) + $month;
    }

    /** @return array{0: int, 1: int} */
    private function periodParts(string $period): array
    {
        return array_map('intval', explode('-', $period));
    }
}
