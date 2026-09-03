<?php

namespace App\Http\Requests\Pengaturan;

use App\Services\KodeBarangGenerator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePengaturanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('pengaturan.kode-barang.update');
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

                $template = (string) $this->input('format_kode_barang');
                preg_match_all('/\{[^{}]*\}/', $template, $matches);

                $tidakDikenal = array_diff($matches[0], KodeBarangGenerator::TOKENS);

                if (! empty($tidakDikenal)) {
                    $validator->errors()->add(
                        'format_kode_barang',
                        'Token tidak dikenal: '.implode(', ', $tidakDikenal).'. Token yang tersedia: '.implode(', ', KodeBarangGenerator::TOKENS).'.'
                    );
                }

                $sisaTemplate = str_replace($matches[0], '', $template);
                if (str_contains($sisaTemplate, '{') || str_contains($sisaTemplate, '}')) {
                    $validator->errors()->add(
                        'format_kode_barang',
                        'Susunan kurung token tidak valid. Gunakan token utuh seperti {UNIT} atau {URUT}.'
                    );
                }

                if (! str_contains($template, '{URUT}')) {
                    $validator->errors()->add(
                        'format_kode_barang',
                        'Template wajib memuat token {URUT} agar setiap barang memperoleh kode unik.'
                    );
                }

                $digits = filter_var($this->input('digit_nomor_urut'), FILTER_VALIDATE_INT);
                if ($digits !== false
                    && $digits >= KodeBarangGenerator::MIN_SEQUENCE_DIGITS
                    && $digits <= KodeBarangGenerator::MAX_SEQUENCE_DIGITS) {
                    $maximumLength = app(KodeBarangGenerator::class)->maximumGeneratedLength(
                        (string) $this->input('format_kode_barang'),
                        $digits,
                    );

                    if ($maximumLength > KodeBarangGenerator::MAX_CODE_LENGTH) {
                        $validator->errors()->add(
                            'format_kode_barang',
                            'Kode hasil template dapat mencapai '.$maximumLength.' karakter, melebihi batas '.KodeBarangGenerator::MAX_CODE_LENGTH.' karakter.'
                        );
                    }
                }
            },
        ];
    }
}
