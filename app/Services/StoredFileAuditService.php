<?php

namespace App\Services;

use App\Models\StoredFile;
use App\Models\StoredFileAudit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoredFileAuditService
{
    public function record(
        StoredFile $file,
        string $event,
        ?User $actor = null,
        ?Request $request = null,
        ?string $context = null,
    ): void {
        $ip = $request?->ip();

        StoredFileAudit::query()->create([
            'stored_file_id' => $file->exists ? $file->id : null,
            'file_uuid' => $file->uuid,
            'koperasi_id' => $file->koperasi_id,
            'actor_id' => $actor?->id,
            'event' => Str::limit($event, 40, ''),
            'ip_hash' => $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null,
            'context' => $context ? Str::limit($context, 120, '') : null,
        ]);
    }
}
