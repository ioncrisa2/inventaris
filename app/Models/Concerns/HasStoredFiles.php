<?php

namespace App\Models\Concerns;

use App\Models\StoredFile;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasStoredFiles
{
    public function storedFiles(): MorphMany
    {
        return $this->morphMany(StoredFile::class, 'owner');
    }
}
