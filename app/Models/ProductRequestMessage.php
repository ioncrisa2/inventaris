<?php

namespace App\Models;

use App\Enums\ProductRequestMessageVisibility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRequestMessage extends Model
{
    protected $fillable = [
        'product_request_id',
        'author_user_id',
        'visibility',
        'body',
    ];

    protected function casts(): array
    {
        return ['visibility' => ProductRequestMessageVisibility::class];
    }

    public function productRequest(): BelongsTo
    {
        return $this->belongsTo(ProductRequest::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProductRequestAttachment::class, 'message_id');
    }
}
