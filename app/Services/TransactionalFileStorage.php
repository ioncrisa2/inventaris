<?php

namespace App\Services;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionalFileStorage
{
    /**
     * Jalankan operasi file/database dalam transaksi tanpa membuat savepoint
     * baru bila pemanggil sudah membuka transaksi. Callback rollback Laravel
     * harus didaftarkan pada transaksi pemilik agar tidak hilang saat savepoint
     * anak di-commit lalu transaksi induk dibatalkan.
     */
    public function transaction(Closure $callback): mixed
    {
        return DB::transactionLevel() > 0
            ? $callback()
            : DB::transaction($callback);
    }

    /**
     * Simpan file dan hapus kembali bila transaksi database terluar rollback.
     */
    public function store(string $disk, string $directory, UploadedFile $file): string
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Penyimpanan file wajib dijalankan di dalam transaksi database.');
        }

        $path = Storage::disk($disk)->putFile($directory, $file);

        if (! is_string($path) || blank($path)) {
            throw new \RuntimeException("Gagal menyimpan file pada disk {$disk}.");
        }

        try {
            DB::afterRollBack(fn () => $this->deleteQuietly($disk, $path));
        } catch (\Throwable $exception) {
            $this->deleteQuietly($disk, $path);

            throw $exception;
        }

        return $path;
    }

    /**
     * Tulis konten ke path deterministik dan pulihkan keadaan sebelumnya bila
     * transaksi rollback. Dipakai fixture seeder yang aman dijalankan ulang.
     */
    public function put(string $disk, string $path, string $contents): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Penulisan file wajib dijalankan di dalam transaksi database.');
        }

        $filesystem = Storage::disk($disk);
        $sudahAda = $filesystem->exists($path);
        $kontenLama = $sudahAda ? $filesystem->get($path) : null;

        if (! $filesystem->put($path, $contents)) {
            throw new \RuntimeException("Gagal menulis file pada disk {$disk}.");
        }

        try {
            DB::afterRollBack(fn () => $this->rollbackPut($disk, $path, $sudahAda, $kontenLama));
        } catch (\Throwable $exception) {
            $this->rollbackPut($disk, $path, $sudahAda, $kontenLama);

            throw $exception;
        }
    }

    /**
     * Hapus file lama hanya setelah transaksi database terluar benar-benar commit.
     */
    public function deleteAfterCommit(string $disk, ?string $path): void
    {
        if (blank($path)) {
            return;
        }

        if (DB::transactionLevel() === 0) {
            $this->deleteQuietly($disk, $path);

            return;
        }

        DB::afterCommit(fn () => $this->deleteQuietly($disk, $path));
    }

    private function deleteQuietly(string $disk, string $path): void
    {
        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function rollbackPut(string $disk, string $path, bool $sudahAda, ?string $kontenLama): void
    {
        try {
            if ($sudahAda) {
                Storage::disk($disk)->put($path, $kontenLama ?? '');
            } else {
                Storage::disk($disk)->delete($path);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
