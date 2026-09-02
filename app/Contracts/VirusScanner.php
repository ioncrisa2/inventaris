<?php

namespace App\Contracts;

interface VirusScanner
{
    /** @param resource $stream */
    public function scan($stream): string;

    public function ping(): bool;
}
