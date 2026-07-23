<?php

namespace App\Http\Requests\Pengaturan;

use App\Services\KodeBarangGenerator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePengaturanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pengaturan.update');
    }

    public function rules(): array
    {
        return [
            'format_kode_barang' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9{}.\-\/_]+$/'],
            'digit_nomor_urut' => [
                'required',
                'integer',
                'min:'.KodeBarangGenerator::MIN_SEQUENCE_DIGITS,
                'max:'.KodeBarangGenerator::MAX_SEQUENCE_DIGITS,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'format_kode_barang.regex' => 'Format hanya boleh berisi huruf, angka, token seperti {TAHUN}, dan tanda pemisah - . _ /.',
            'digit_nomor_urut.min' => 'Jumlah digit nomor urut minimal :min digit.',
            'digit_nomor_urut.max' => 'Jumlah digit nomor urut maksimal :max digit.',
        ];
    }

    /**
     * Pastikan hanya token yang dikenal KodeBarangGenerator yang dipakai di template.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('format_kode_barang')) {
                    return;
                }

                preg_match_all('/\{[A-Z_]+\}/', (string) $this->input('format_kode_barang'), $matches);

                $tidakDikenal = array_diff($matches[0], KodeBarangGenerator::TOKENS);

                if (! empty($tidakDikenal)) {
                    $validator->errors()->add(
                        'format_kode_barang',
                        'Token tidak dikenal: '.implode(', ', $tidakDikenal).'. Token yang tersedia: '.implode(', ', KodeBarangGenerator::TOKENS).'.'
                    );
                }

                if (! str_contains((string) $this->input('format_kode_barang'), '{URUT}')) {
                    $validator->errors()->add(
                        'format_kode_barang',
                        'Template wajib memuat token {URUT} agar setiap barang memperoleh kode unik.'
                    );
                }
            },
        ];
    }
}
