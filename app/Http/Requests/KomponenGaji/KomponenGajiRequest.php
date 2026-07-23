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
                        && $this->input('metode_perhitungan') === 'persentase'
                        && bccomp($nilai, '100', 2) > 0) {
                        $fail('Nilai persentase harus berada antara 0 sampai 100.');
                    }
                },
            ],
        ];
    }
}
