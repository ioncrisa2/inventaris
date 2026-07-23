<?php

namespace App\Http\Requests\Karyawan;

use App\Models\Karyawan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmploymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('karyawan')) ?? false;
    }

    public function rules(): array
    {
        /** @var Karyawan $karyawan */
        $karyawan = $this->route('karyawan');
        $tanggalMasuk = $karyawan->tanggal_masuk_kerja?->toDateString();
        $tanggalRules = [
            Rule::requiredIf(blank($karyawan->tanggal_mengundurkan_diri)),
            'nullable',
            'date',
            'before_or_equal:today',
        ];

        if ($tanggalMasuk) {
            $tanggalRules[] = 'after_or_equal:'.$tanggalMasuk;
        }

        return [
            '_modal' => ['nullable', 'string'],
            'tanggal_mengundurkan_diri' => $tanggalRules,
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_mengundurkan_diri.required' => 'Tanggal keluar wajib diisi untuk menonaktifkan karyawan.',
            'tanggal_mengundurkan_diri.after_or_equal' => 'Tanggal keluar tidak boleh sebelum tanggal masuk kerja.',
            'tanggal_mengundurkan_diri.before_or_equal' => 'Tanggal keluar tidak boleh melewati hari ini.',
        ];
    }
}
