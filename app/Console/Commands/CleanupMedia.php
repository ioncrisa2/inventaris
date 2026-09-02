<?php

namespace App\Console\Commands;

use App\Services\MediaCleanupService;
use Illuminate\Console\Command;

class CleanupMedia extends Command
{
    protected $signature = 'media:cleanup
        {--dry-run : Laporkan tanpa mengubah database atau storage}
        {--stalled-only : Hanya antrekan ulang scan yang tertahan}
        {--chunk=200 : Jumlah record per batch}';

    protected $description = 'Membersihkan staging kedaluwarsa dan menjaga pipeline media tetap sehat';

    public function handle(MediaCleanupService $cleanup): int
    {
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5000],
        ]);
        if ($chunk === false) {
            $this->error('Nilai --chunk harus berupa angka 1 sampai 5000.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        if ((bool) $this->option('stalled-only')) {
            $count = $cleanup->requeueStalled($dryRun, $chunk);
            $this->components->info(($dryRun ? 'Scan tertahan ditemukan: ' : 'Scan tertahan diantrekan ulang: ').$count);

            return self::SUCCESS;
        }

        $stats = $cleanup->run($dryRun, $chunk);
        $this->table(
            ['Staging kedaluwarsa', 'Status terminal', 'Owner yatim', 'Referensi hilang', 'Scan diantrekan ulang'],
            [[$stats['expired'], $stats['terminal'], $stats['orphaned'], $stats['missing'], $stats['requeued']]],
        );
        if ($dryRun) {
            $this->components->info('Dry-run selesai; tidak ada file atau record yang diubah.');
        }

        return self::SUCCESS;
    }
}
