<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateSlipGaji extends Model
{
    protected $table = 'template_slip_gaji';

    protected $fillable = [
        'nama',
        'konfigurasi_draf',
        'konfigurasi_terbit',
        'revisi_draf',
        'revisi_terbit',
        'draf_diubah_oleh',
        'diterbitkan_oleh',
        'diterbitkan_pada',
    ];

    protected function casts(): array
    {
        return [
            'konfigurasi_draf' => 'array',
            'konfigurasi_terbit' => 'array',
            'revisi_draf' => 'integer',
            'revisi_terbit' => 'integer',
            'diterbitkan_pada' => 'datetime',
        ];
    }

    public function pengubahDraf()
    {
        return $this->belongsTo(User::class, 'draf_diubah_oleh');
    }

    public function penerbit()
    {
        return $this->belongsTo(User::class, 'diterbitkan_oleh');
    }
}
