<?php

namespace App\Support;

final class PreparedUpload
{
    /**
     * @param  array<string, array{path:string, temporary:bool, mime_type:string, extension:string, size_bytes:int, checksum_sha256:string, width:?int, height:?int}>  $variants
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $policy,
        public readonly string $originalName,
        public readonly string $sourceMimeType,
        public readonly string $sourceExtension,
        public readonly int $sourceSizeBytes,
        public readonly string $sourceChecksumSha256,
        public readonly ?int $sourceWidth,
        public readonly ?int $sourceHeight,
        public readonly array $variants,
    ) {}

    public function cleanup(): void
    {
        foreach ($this->variants as $variant) {
            if ($variant['temporary'] && is_file($variant['path'])) {
                @unlink($variant['path']);
            }
        }
    }
}
