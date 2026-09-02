<?php

namespace App\Models;

use App\Models\Concerns\HasStoredFiles;
use Illuminate\Database\Eloquent\Model;

class FotoBarang extends Model
{
    use HasStoredFiles;

    protected $table = 'foto_barang';

    protected $fillable = [
        'barang_id',
        'path',
        'keterangan',
    ];

    public function barang()
    {
        return $this->belongsTo(Barang::class);
    }
}
