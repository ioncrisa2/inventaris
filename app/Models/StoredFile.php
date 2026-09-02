<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StoredFile extends Model
{
    use HasUuids;

    protected $fillable = [
        'uuid', 'koperasi_id', 'uploaded_by', 'policy', 'collection', 'status', 'scan_status',
        'staging_disk', 'staging_path', 'disk', 'path', 'original_name', 'mime_type', 'extension',
        'source_size_bytes', 'final_size_bytes', 'source_checksum_sha256', 'final_checksum_sha256',
        'width', 'height', 'failure_code', 'metadata', 'claimed_at', 'scanned_at', 'processed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'source_size_bytes' => 'integer',
            'final_size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'metadata' => 'array',
            'claimed_at' => 'datetime',
            'scanned_at' => 'datetime',
            'processed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(StoredFileVariant::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'ready'
            && in_array($this->scan_status, ['clean', 'not_required'], true);
    }
}
