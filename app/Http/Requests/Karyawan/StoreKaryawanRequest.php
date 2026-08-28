<?php

namespace App\Http\Requests\Karyawan;

use App\Models\Karyawan;
use App\Rules\Decimal15Two;
use App\Support\TenantRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKaryawanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Data Identitas
            'nik' => ['required', 'string', 'max:20', TenantRule::unique('karyawan', 'nik')],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'tempat_lahir' => ['required', 'string', 'max:255'],
            'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', Rule::in(config('kepegawaian.jenis_kelamin'))],
            'agama' => ['required', Rule::in(config('kepegawaian.agama'))],
            'status_perkawinan' => ['required', Rule::in(config('kepegawaian.status_perkawinan'))],
            'nomor_ktp' => ['required', 'digits:16', TenantRule::unique('karyawan', 'nomor_ktp')],
            'npwp' => ['nullable', 'string', 'max:30'],
            'pendidikan_terakhir' => ['required', Rule::in(config('kepegawaian.pendidikan_terakhir'))],
            'jurusan' => ['nullable', 'string', 'max:255'],
            'nama_sekolah' => ['nullable', 'string', 'max:255'],
            'tahun_lulus' => ['nullable', 'integer', 'min:1950', 'max:'.now()->year],
            'nama_pasangan' => ['nullable', 'string', 'max:255'],
            'jumlah_anak' => ['nullable', 'integer', 'min:0'],
            'alamat_ktp' => ['required', 'string', 'max:2000'],
            'alamat_domisili' => ['required_without:alamat_sama_ktp', 'nullable', 'string', 'max:2000'],
            'foto_karyawan' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // Data Kepegawaian
            'unit_kerja_id' => ['required', TenantRule::exists('unit_kerja')],
            'tanggal_masuk_kerja' => ['required', 'date', 'before_or_equal:today'],
            'jabatan' => ['required', 'string', 'max:255'],
            'status_karyawan' => ['required', Rule::in(Karyawan::STATUSES)],
            'nomor_sk_pengangkatan' => ['nullable', 'string', 'max:255'],
            'tanggal_sk_pengangkatan' => ['nullable', 'date', 'before_or_equal:today'],
            'atasan_langsung_id' => ['nullable', TenantRule::exists('karyawan')],
            'gaji_pokok' => ['required', new Decimal15Two],

            'dokumen' => ['nullable', 'array'],
            'dokumen.*.jenis_dokumen' => ['nullable', 'required_with:dokumen.*.dokumen', Rule::in(config('kepegawaian.jenis_dokumen'))],
            'dokumen.*.dokumen' => ['nullable', 'required_with:dokumen.*.jenis_dokumen', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! ($this->user()?->can('karyawan.gaji.update') ?? false)) {
            $this->merge(['gaji_pokok' => '0']);
        }
    }
}
