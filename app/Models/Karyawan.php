<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Karyawan extends Model
{
    /**
     * Jenis kepegawaian yang valid. Terpisah dari status aktif/keluar —
     * karyawan yang sudah keluar ditandai lewat `tanggal_mengundurkan_diri`,
     * bukan lewat status ini (lihat STATUS_COLORS & kolom terkait).
     */
    public const STATUSES = ['Tetap', 'Kontrak', 'Honorer'];

    /**
     * Warna badge Bootstrap per status karyawan, dipakai bareng oleh
     * beberapa view (karyawan/index, absensi/index, absensi/show) supaya
     * pemetaan status->warna hanya didefinisikan sekali.
     */
    public const STATUS_COLORS = [
        'Tetap' => 'bg-success',
        'Kontrak' => 'bg-warning text-dark',
        'Honorer' => 'bg-info text-dark',
    ];

    protected $table = 'karyawan';

    protected $fillable = [
        'nik',
        'nama_lengkap',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'status_perkawinan',
        'nomor_ktp',
        'npwp',
        'pendidikan_terakhir',
        'jurusan',
        'nama_sekolah',
        'tahun_lulus',
        'nama_pasangan',
        'jumlah_anak',
        'tanggal_mengundurkan_diri',
        'foto_karyawan',
        'unit_kerja_id',
        'tanggal_masuk_kerja',
        'jabatan',
        'status_karyawan',
        'nomor_sk_pengangkatan',
        'tanggal_sk_pengangkatan',
        'atasan_langsung_id',
        'gaji_pokok',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_mengundurkan_diri' => 'date',
        'tanggal_masuk_kerja' => 'date',
        'tanggal_sk_pengangkatan' => 'date',
        'gaji_pokok' => 'decimal:2',
    ];

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class);
    }

    public function atasanLangsung()
    {
        return $this->belongsTo(self::class, 'atasan_langsung_id');
    }

    public function absensis()
    {
        return $this->hasMany(Absensi::class);
    }

    public function dokumen()
    {
        return $this->hasMany(DokumenKaryawan::class);
    }

    public function transaksiGaji()
    {
        return $this->hasMany(TransaksiGaji::class);
    }

    public function bawahanLangsung()
    {
        return $this->hasMany(self::class, 'atasan_langsung_id');
    }
}
