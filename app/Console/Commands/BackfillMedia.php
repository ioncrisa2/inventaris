<?php

namespace App\Console\Commands;

use App\Services\MediaBackfillService;
use Illuminate\Console\Command;

class BackfillMedia extends Command
{
    protected $signature = 'media:backfill
        {--dry-run : Periksa file tanpa menulis registry}
        {--chunk=200 : Jumlah record yang diproses per batch}';

    protected $description = 'Mencatat file legacy ke registry media secara idempotent';

    public function handle(MediaBackfillService $backfill): int
    {
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5000],
        ]);
        if ($chunk === false) {
            $this->error('Nilai --chunk harus berupa angka 1 sampai 5000.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $this->components->info($dryRun ? 'Memeriksa media (dry-run).' : 'Mendaftarkan media legacy.');
        $progress = $this->output->createProgressBar($backfill->countCandidates());
        $progress->start();
        $stats = $backfill->run($dryRun, $chunk, function () use ($progress): void {
            $progress->advance();
        });
        $progress->finish();
        $this->newLine(2);

        $this->table(['Dipindai', $dryRun ? 'Siap didaftarkan' : 'Didaftarkan', 'Sudah ada', 'Hilang', 'Tak terbaca', 'Invalid'], [[
            $stats['scanned'],
            $stats['created'],
            $stats['existing'],
            $stats['missing'],
            $stats['unreadable'],
            $stats['invalid'],
        ]]);

        foreach ($stats['details'] as $detail) {
            $this->components->warn($detail);
        }

        if ($dryRun) {
            $this->components->info('Dry-run selesai; database tidak diubah.');
        }

        return ($stats['unreadable'] + $stats['invalid']) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
