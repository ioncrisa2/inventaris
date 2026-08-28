<?php

namespace App\Notifications;

use App\Models\TransaksiGaji;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SalarySlipPublished extends Notification
{
    use Queueable;

    public function __construct(private readonly TransaksiGaji $transaksiGaji) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'penggajian',
            'priority' => 'info',
            'event' => 'salary_slip_published',
            'title' => 'Slip gaji diterbitkan',
            'body' => 'Slip gaji '.Carbon::create($this->transaksiGaji->tahun, $this->transaksiGaji->bulan, 1)->translatedFormat('F Y').' sudah tersedia.',
            'url' => route('me.salary-slips.show', $this->transaksiGaji),
        ];
    }
}
