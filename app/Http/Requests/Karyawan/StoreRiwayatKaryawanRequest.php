<?php

namespace App\Http\Requests\Karyawan;

use App\Models\Karyawan;
use App\Rules\Decimal15Two;
use App\Support\KaryawanPerubahanSchema;
use App\Support\TenantRule;
use App\Support\UploadPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiwayatKaryawanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $jenis = $this->input('jenis_perubahan');
        $schema = KaryawanPerubahanSchema::types()[$jenis] ?? null;

        return $schema !== null
            && ($this->user()?->can($schema['permission']) ?? false)
            && ($this->user()?->can('karyawan.view') ?? false);
    }

    public function rules(): array
    {
        /** @var Karyawan|null $karyawan */
        $karyawan = $this->route('karyawan');
        $jenis = $this->input('jenis_perubahan');
        $schema = KaryawanPerubahanSchema::types()[$jenis] ?? null;
        $dokumenWajib = (bool) ($schema['dokumen_wajib'] ?? false);

        $rules = [
            'jenis_perubahan' => ['required', Rule::in(array_keys(KaryawanPerubahanSchema::types()))],
            'tanggal_berlaku' => [
                'required',
                'date',
                'before_or_equal:today',
                ...($karyawan?->tanggal_masuk_kerja
                    ? ['after_or_equal:'.$karyawan->tanggal_masuk_kerja->toDateString()]
                    : []),
            ],
            'alasan' => ['required', 'string', 'max:1000'],
            'dokumen_pendukung' => [
                Rule::requiredIf($dokumenWajib && empty($this->input('dokumen_pendukung_upload_uuids'))),
                ...UploadPolicy::collectionRules('business_documents'),
            ],
            'dokumen_pendukung.*' => UploadPolicy::fileRules('business_documents', true),
            'dokumen_pendukung_upload_uuids' => [
                Rule::requiredIf($dokumenWajib && empty($this->file('dokumen_pendukung'))),
                'nullable',
                'array',
                'max:5',
            ],
            'dokumen_pendukung_upload_uuids.*' => UploadPolicy::tokenRules('business_documents'),
        ];

        return match ($jenis) {
            'data_pribadi' => [
                ...$rules,
                'nik' => [
                    'required',
                    'string',
                    'max:20',
                    TenantRule::uniqueFor('karyawan', 'nik', $karyawan?->koperasi_id)
                        ->ignore($karyawan?->id),
                ],
                'nama_lengkap' => ['required', 'string', 'max:255'],
                'tempat_lahir' => ['required', 'string', 'max:255'],
                'tanggal_lahir' => ['required', 'date', 'before_or_equal:today'],
                'jenis_kelamin' => ['required', Rule::in(config('kepegawaian.jenis_kelamin'))],
                'agama' => ['required', Rule::in(config('kepegawaian.agama'))],
                'nomor_ktp' => [
                    'required',
                    'digits:16',
                    TenantRule::uniqueFor('karyawan', 'nomor_ktp', $karyawan?->koperasi_id)
                        ->ignore($karyawan?->id),
                ],
                'npwp' => ['required', 'string', 'max:30'],
                'alamat_ktp' => ['required', 'string', 'max:2000'],
                'alamat_domisili' => ['required', 'string', 'max:2000'],
                'status_perkawinan' => ['required', Rule::in(config('kepegawaian.status_perkawinan'))],
                'pendidikan_terakhir' => ['required', Rule::in(config('kepegawaian.pendidikan_terakhir'))],
                'jurusan' => ['required', 'string', 'max:255'],
                'nama_sekolah' => ['required', 'string', 'max:255'],
                'tahun_lulus' => ['required', 'integer', 'min:1950', 'max:'.now()->year],
                'nama_pasangan' => ['nullable', 'string', 'max:255'],
                'jumlah_anak' => ['nullable', 'integer', 'min:0'],
                'foto_karyawan' => UploadPolicy::fileRules('employee_photo'),
                'foto_karyawan_upload_uuid' => UploadPolicy::tokenRules('employee_photo'),
            ],
            'hubungan_kerja' => [
                ...$rules,
                'tanggal_masuk_kerja' => [
                    'required',
                    'date',
                    'before_or_equal:today',
                    'before_or_equal:tanggal_berlaku',
                ],
                'status_karyawan' => ['required', Rule::in(Karyawan::STATUSES)],
                'nomor_sk_pengangkatan' => ['required', 'string', 'max:255'],
                'tanggal_sk_pengangkatan' => ['required', 'date', 'before_or_equal:today'],
            ],
            'mutasi_promosi' => [
                ...$rules,
                'unit_kerja_id' => [
                    'required',
                    TenantRule::existsFor('unit_kerja', 'id', $karyawan?->koperasi_id),
                ],
                'jabatan' => ['required', 'string', 'max:255'],
                'atasan_langsung_id' => [
                    'nullable',
                    TenantRule::existsFor('karyawan', 'id', $karyawan?->koperasi_id),
                    Rule::notIn([$karyawan?->id]),
                ],
                'nomor_sk_pengangkatan' => ['required', 'string', 'max:255'],
                'tanggal_sk_pengangkatan' => ['required', 'date', 'before_or_equal:today'],
            ],
            'penyesuaian_gaji' => [
                ...$rules,
                'gaji_pokok' => ['required', new Decimal15Two],
            ],
            'keaktifan' => [
                ...$rules,
                'status_keaktifan_baru' => ['required', Rule::in(['aktif', 'nonaktif'])],
                'tanggal_mengundurkan_diri' => [
                    Rule::requiredIf($this->input('status_keaktifan_baru') === 'nonaktif'),
                    'nullable',
                    'date',
                    'before_or_equal:today',
                    ...($karyawan?->tanggal_masuk_kerja
                        ? ['after_or_equal:'.$karyawan->tanggal_masuk_kerja->toDateString()]
                        : []),
                ],
            ],
            default => $rules,
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('jenis_perubahan') !== 'keaktifan') {
            return;
        }

        $this->merge([
            'tanggal_mengundurkan_diri' => $this->input('status_keaktifan_baru') === 'nonaktif'
                ? $this->input('tanggal_berlaku')
                : null,
        ]);
    }

    public function messages(): array
    {
        return [
            'tanggal_berlaku.before_or_equal' => 'Tanggal berlaku tidak boleh melewati hari ini.',
            'tanggal_berlaku.after_or_equal' => 'Tanggal berlaku tidak boleh sebelum tanggal masuk kerja.',
            'alasan.required' => 'Alasan perubahan wajib dijelaskan.',
            'dokumen_pendukung.required' => 'Dokumen pendukung wajib diunggah untuk jenis perubahan ini.',
            'dokumen_pendukung.max' => 'Maksimal 5 dokumen pendukung dalam satu perubahan.',
            'tanggal_mengundurkan_diri.required' => 'Tanggal keluar wajib diisi saat karyawan dinonaktifkan.',
            'tanggal_mengundurkan_diri.after_or_equal' => 'Tanggal keluar tidak boleh sebelum tanggal masuk kerja.',
        ];
    }
}
