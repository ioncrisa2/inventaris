<?php

namespace App\Models;

use App\Models\Concerns\BelongsToKoperasi;
use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    use BelongsToKoperasi;

    public const STATUSES = [
        'Hadir',
        'Izin',
        'Sakit',
        'Cuti',
        'Dinas',
        'Alpha',
    ];

    public const DINAS_STATUSES = [
        'Dinas',
        'Dinas Luar',
        'Dinas Luar Kota',
    ];

    public const GAJI_HARI_KERJA_STATUSES = [
        'Hadir',
        'Dinas',
        'Dinas Luar',
        'Dinas Luar Kota',
    ];

    /** Status yang tetap masuk akal dicatat pada hari libur (Minggu/nasional). */
    public const LIBUR_ALLOWED_STATUSES = [
        'Izin',
        'Sakit',
        'Dinas',
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
        'Dinas' => 'bg-secondary',
        'Dinas Luar' => 'bg-secondary',
        'Dinas Luar Kota' => 'bg-secondary',
        'Alpha' => 'bg-danger',
    ];

    /** Label ringkas agar status panjang tetap terbaca di sel kalender. */
    public const CALENDAR_LABELS = [
        'Dinas' => 'Dinas',
        'Dinas Luar' => 'Dinas',
        'Dinas Luar Kota' => 'Dinas',
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

    public static function normalizeStatus(string $status): string
    {
        return in_array($status, self::DINAS_STATUSES, true) ? 'Dinas' : $status;
    }

    public function setStatusAttribute(string $status): void
    {
        $this->attributes['status'] = self::normalizeStatus($status);
    }

    public function karyawan()
    {
        return $this->belongsTo(Karyawan::class);
    }
}
