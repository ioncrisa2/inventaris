<?php

namespace App\Services;

use App\Contracts\VirusScanner;
use App\Exceptions\ScannerUnavailableException;

class ClamAvScanner implements VirusScanner
{
    public function scan($stream): string
    {
        if (! is_resource($stream)) {
            throw new \InvalidArgumentException('Stream pemindaian tidak valid.');
        }

        $socket = $this->connect();

        try {
            $this->writeAll($socket, "zINSTREAM\0");
            $chunkBytes = max(1024, (int) config('uploads.clamav.chunk_bytes', 8192));
            while (! feof($stream)) {
                $chunk = fread($stream, $chunkBytes);
                if ($chunk === false) {
                    throw new ScannerUnavailableException('File tidak dapat dibaca saat pemindaian.');
                }
                if ($chunk === '') {
                    continue;
                }
                $this->writeAll($socket, pack('N', strlen($chunk)).$chunk);
            }
            $this->writeAll($socket, pack('N', 0));
            $response = $this->readResponse($socket);
        } finally {
            fclose($socket);
        }

        if (str_contains($response, ' FOUND')) {
            return 'infected';
        }
        if (str_contains($response, ' OK')) {
            return 'clean';
        }

        throw new ScannerUnavailableException('ClamAV mengembalikan respons yang tidak dapat dipakai.');
    }

    public function ping(): bool
    {
        try {
            $socket = $this->connect();
            $this->writeAll($socket, "zPING\0");
            $response = $this->readResponse($socket);
            fclose($socket);

            return str_contains($response, 'PONG');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return resource */
    private function connect()
    {
        $host = (string) config('uploads.clamav.host', 'clamav');
        $port = (int) config('uploads.clamav.port', 3310);
        $timeout = (float) config('uploads.clamav.connect_timeout_seconds', 3);
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errorNumber, $errorMessage, $timeout);

        if (! is_resource($socket)) {
            throw new ScannerUnavailableException('ClamAV tidak tersedia.');
        }

        stream_set_timeout($socket, max(1, (int) config('uploads.clamav.read_timeout_seconds', 60)));

        return $socket;
    }

    /** @param resource $socket */
    private function writeAll($socket, string $bytes): void
    {
        $remaining = $bytes;
        while ($remaining !== '') {
            $written = fwrite($socket, $remaining);
            if (! is_int($written) || $written < 1) {
                throw new ScannerUnavailableException('Koneksi ClamAV terputus.');
            }
            $remaining = substr($remaining, $written);
        }
    }

    /** @param resource $socket */
    private function readResponse($socket): string
    {
        $response = '';
        while (strlen($response) < 4096) {
            $chunk = fread($socket, 512);
            if ($chunk === false) {
                throw new ScannerUnavailableException('Respons ClamAV tidak dapat dibaca.');
            }
            $response .= $chunk;
            if (str_contains($response, "\0") || str_contains($response, "\n")) {
                break;
            }
            $meta = stream_get_meta_data($socket);
            if (($meta['timed_out'] ?? false) || feof($socket)) {
                break;
            }
        }

        if ($response === '') {
            throw new ScannerUnavailableException('ClamAV tidak merespons tepat waktu.');
        }

        return trim($response, "\0\r\n ");
    }
}
