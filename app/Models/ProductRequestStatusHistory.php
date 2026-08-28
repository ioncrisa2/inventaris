<?php

namespace App\Models;

use App\Enums\ProductRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProductRequestStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_request_id',
        'changed_by',
        'from_status',
        'to_status',
        'reason',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Riwayat status bersifat immutable.'));
        static::deleting(fn () => throw new LogicException('Riwayat status bersifat immutable.'));
    }

    protected function casts(): array
    {
        return [
            'from_status' => ProductRequestStatus::class,
            'to_status' => ProductRequestStatus::class,
            'created_at' => 'datetime',
        ];
    }

    public function productRequest(): BelongsTo
    {
        return $this->belongsTo(ProductRequest::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
