<?php

namespace App\Http\Requests\Owner;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OwnerAnalyticsRequest extends FormRequest
{
    public const DEFAULT_MONTHS = 12;

    public const MAX_MONTHS = 24;

    public const MODULES = ['semua', 'inventaris', 'kepegawaian', 'absensi', 'penggajian'];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && method_exists($user, 'isSystemOwner')
            && $user->isSystemOwner();
    }

    protected function prepareForValidation(): void
    {
        $today = CarbonImmutable::today(config('app.timezone'));
        $routeKoperasi = $this->route('koperasi');

        if (is_object($routeKoperasi) && method_exists($routeKoperasi, 'getKey')) {
            $routeKoperasi = $routeKoperasi->getKey();
        }

        $defaults = [
            'tanggal_awal' => $today->startOfMonth()->subMonths(self::DEFAULT_MONTHS - 1)->toDateString(),
            'tanggal_akhir' => $today->toDateString(),
            'modul' => 'semua',
        ];

        if ($routeKoperasi !== null && $routeKoperasi !== '') {
            $defaults['koperasi_id'] = $routeKoperasi;
        }

        $this->merge(array_merge($defaults, $this->all(), $routeKoperasi !== null ? [
            'koperasi_id' => $routeKoperasi,
        ] : []));
    }

    public function rules(): array
    {
        return [
            'tanggal_awal' => ['required', 'date_format:Y-m-d', 'before_or_equal:tanggal_akhir'],
            'tanggal_akhir' => ['required', 'date_format:Y-m-d', 'after_or_equal:tanggal_awal', 'before_or_equal:today'],
            'koperasi_id' => ['nullable', 'integer', Rule::exists('koperasi', 'id')],
            'modul' => ['required', Rule::in(self::MODULES)],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['tanggal_awal', 'tanggal_akhir'])) {
                    return;
                }

                $start = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('tanggal_awal'));
                $end = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('tanggal_akhir'));

                if ($start->lt($end->subMonthsNoOverflow(self::MAX_MONTHS))) {
                    $validator->errors()->add(
                        'tanggal_awal',
                        'Rentang analitik tidak boleh lebih dari '.self::MAX_MONTHS.' bulan.',
                    );
                }
            },
        ];
    }

    /**
     * @return array{tanggal_awal: string, tanggal_akhir: string, koperasi_id: ?int, modul: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'tanggal_awal' => (string) $validated['tanggal_awal'],
            'tanggal_akhir' => (string) $validated['tanggal_akhir'],
            'koperasi_id' => isset($validated['koperasi_id']) ? (int) $validated['koperasi_id'] : null,
            'modul' => (string) $validated['modul'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_awal.before_or_equal' => 'Tanggal awal harus sebelum atau sama dengan tanggal akhir.',
            'tanggal_akhir.after_or_equal' => 'Tanggal akhir harus setelah atau sama dengan tanggal awal.',
            'tanggal_akhir.before_or_equal' => 'Tanggal akhir tidak boleh melewati hari ini.',
            'koperasi_id.exists' => 'Koperasi yang dipilih tidak ditemukan.',
            'modul.in' => 'Modul analitik tidak valid.',
        ];
    }
}
