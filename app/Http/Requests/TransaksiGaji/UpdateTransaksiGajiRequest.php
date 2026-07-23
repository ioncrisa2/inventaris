<?php

namespace App\Http\Requests\TransaksiGaji;

class UpdateTransaksiGajiRequest extends StoreTransaksiGajiRequest
{
    protected function prepareForValidation(): void
    {
        if ($transaksiGaji = $this->route('transaksi_gaji')) {
            $this->merge(['karyawan_id' => $transaksiGaji->karyawan_id]);
        }
    }

    protected function transaksiGajiId(): ?int
    {
        return $this->route('transaksi_gaji')?->id;
    }
}
