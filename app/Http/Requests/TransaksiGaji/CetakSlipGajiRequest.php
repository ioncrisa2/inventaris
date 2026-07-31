<?php

namespace App\Http\Requests\TransaksiGaji;

use App\Support\SlipGajiPaperLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CetakSlipGajiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaksiGaji = $this->route('transaksiGaji');

        return $transaksiGaji
            ? $this->user()->can('cetak', $transaksiGaji)
            : $this->user()->can('transaksi-gaji.cetak');
    }

    public function rules(): array
    {
        return [
            ...$this->commonRules(),
            'mode' => ['prohibited'],
            'bulan' => ['prohibited'],
            'tahun' => ['prohibited'],
            'karyawan_id' => ['prohibited'],
            'periode_awal' => ['prohibited'],
            'periode_akhir' => ['prohibited'],
        ];
    }

    protected function commonRules(): array
    {
        $penandaTanganAktif = fn () => Rule::exists('karyawan', 'id')
            ->whereNull('tanggal_mengundurkan_diri');

        return [
            'dibuat_oleh_id' => ['required', 'integer', 'different:mengetahui_id', $penandaTanganAktif()],
            'mengetahui_id' => ['required', 'integer', 'different:dibuat_oleh_id', $penandaTanganAktif()],
            'paper_layout' => ['nullable', Rule::in(SlipGajiPaperLayout::VALUES)],
        ];
    }

    public function messages(): array
    {
        return [
            'dibuat_oleh_id.required' => 'Pilih karyawan yang membuat slip.',
            'dibuat_oleh_id.exists' => 'Penanda tangan bagian Dibuat oleh harus merupakan karyawan aktif.',
            'dibuat_oleh_id.different' => 'Penanda tangan Dibuat oleh dan Mengetahui harus berbeda.',
            'mengetahui_id.required' => 'Pilih karyawan yang mengetahui slip.',
            'mengetahui_id.exists' => 'Penanda tangan bagian Mengetahui harus merupakan karyawan aktif.',
            'mengetahui_id.different' => 'Penanda tangan Dibuat oleh dan Mengetahui harus berbeda.',
            'paper_layout.in' => 'Pembagian kertas harus kiri–kanan atau atas–bawah.',
        ];
    }

    /** @return array{0: int, 1: int} */
    public function penandaTanganIds(): array
    {
        return [
            (int) $this->validated('dibuat_oleh_id'),
            (int) $this->validated('mengetahui_id'),
        ];
    }

    public function paperLayout(): ?string
    {
        $layout = $this->validated('paper_layout');

        return is_string($layout) ? $layout : null;
    }
}
