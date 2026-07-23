<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    public const STATUSES = [
        'Hadir',
        'Izin',
        'Sakit',
        'Cuti',
        'Dinas Luar Kota',
        'Alpha',
    ];

    /** Status yang tetap masuk akal dicatat pada hari Minggu. */
    public const SUNDAY_ALLOWED_STATUSES = [
        'Izin',
        'Sakit',
        'Dinas Luar Kota',
    ];

    /**
     * Warna badge Bootstrap per status absensi, dipakai bareng oleh
     * tampilan kalender absensi supaya pemetaan status->warna hanya
     * didefinisikan sekali.
     */
    public const STATUS_COLORS = [
        'Hadir' => 'bg-success',
        'Izin' => 'bg-warning text-dark',
        'Sakit' => 'bg-info text-dark',
        'Cuti' => 'bg-primary',
        'Dinas Luar Kota' => 'bg-secondary',
        'Alpha' => 'bg-danger',
    ];

    /** Label ringkas agar status panjang tetap terbaca di sel kalender. */
    public const CALENDAR_LABELS = [
        'Dinas Luar Kota' => 'Dinas Luar',
    ];

    protected $table = 'absensi';

    protected $fillable = [
        'karyawan_id',
        'tanggal',
        'status',
        'catatan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }
}
