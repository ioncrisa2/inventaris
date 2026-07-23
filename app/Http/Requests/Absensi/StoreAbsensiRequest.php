<?php

namespace App\Http\Requests\Absensi;

use App\Models\Absensi;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAbsensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('absensi.create');
    }

    public function rules(): array
    {
        return [
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(Absensi::STATUSES)],
            'catatan' => ['nullable', 'string'],
        ];
    }

    /**
     * Hari Minggu hanya menerima status non-hari-kerja yang relevan.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->filled('tanggal') || ! $this->filled('status')) {
                    return;
                }

                $tanggalAbsensi = Carbon::parse($this->input('tanggal'));
                $statusDiizinkanDiHariLibur = in_array($this->input('status'), Absensi::SUNDAY_ALLOWED_STATUSES, true);

                if ($tanggalAbsensi->isSunday() && ! $statusDiizinkanDiHariLibur) {
                    $validator->errors()->add('absensi', 'Hari Minggu hanya dapat dicatat dengan status Izin, Sakit, atau Dinas Luar Kota.');
                }
            },
        ];
    }
}
