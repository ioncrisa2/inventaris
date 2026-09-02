<?php

namespace App\Http\Requests\KomponenGaji;

use App\Models\KomponenGaji;
use App\Rules\Decimal15Two;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KomponenGajiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_komponen' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(['Tunjangan', 'Potongan'])],
            'metode_perhitungan' => ['required', Rule::in(array_keys(KomponenGaji::METODE_PERHITUNGAN))],
            'nilai_default' => [
                'required',
                new Decimal15Two,
                function ($attribute, $value, $fail) {
                    $nilai = Decimal15Two::normalizeNonNegative($value);

                    if ($nilai !== null
                        && in_array($this->input('metode_perhitungan'), ['persentase', 'persentase_pengali'], true)
                        && bccomp($nilai, '100', 2) > 0) {
                        $fail('Nilai persentase harus berada antara 0 sampai 100.');
                    }
                },
            ],
            'rincian' => [
                'exclude_unless:metode_perhitungan,'.KomponenGaji::METODE_DAFTAR_TETAP,
                'required',
                'array',
                'min:1',
                'max:100',
            ],
            'rincian.*' => ['array'],
            'rincian.*.keterangan' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'rincian.*.nominal' => ['required', new Decimal15Two],
        ];
    }

    public function messages(): array
    {
        return [
            'rincian.required' => 'Tambahkan minimal satu rincian keterangan dan nominal.',
            'rincian.min' => 'Tambahkan minimal satu rincian keterangan dan nominal.',
            'rincian.max' => 'Maksimal 100 rincian dalam satu komponen.',
            'rincian.*.keterangan.required' => 'Keterangan wajib diisi.',
            'rincian.*.keterangan.max' => 'Keterangan maksimal 255 karakter.',
            'rincian.*.keterangan.distinct' => 'Keterangan dalam satu komponen tidak boleh sama.',
            'rincian.*.nominal.required' => 'Nominal wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('metode_perhitungan') === KomponenGaji::METODE_DAFTAR_TETAP
            && is_array($this->input('rincian'))) {
            $this->merge([
                'rincian' => collect($this->input('rincian'))
                    ->map(fn ($item) => is_array($item)
                        ? [...$item, 'keterangan' => trim((string) ($item['keterangan'] ?? ''))]
                        : $item)
                    ->all(),
            ]);
        }

        if (in_array($this->input('metode_perhitungan'), [
            ...KomponenGaji::METODE_INPUT_TRANSAKSI,
            KomponenGaji::METODE_DAFTAR_TETAP,
        ], true)) {
            $this->merge(['nilai_default' => '0']);
        }
    }
}
