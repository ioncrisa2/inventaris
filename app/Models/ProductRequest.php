<?php

namespace App\Models;

use App\Enums\ProductRequestPriority;
use App\Enums\ProductRequestStatus;
use App\Enums\ProductRequestType;
use App\Models\Scopes\KoperasiScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductRequest extends Model
{
    protected $fillable = [
        'ticket_number',
        'koperasi_id',
        'created_by',
        'assigned_to',
        'type',
        'module',
        'title',
        'description',
        'requester_priority',
        'internal_priority',
        'status',
        'duplicate_of_id',
        'first_responded_at',
        'last_activity_at',
        'resolved_at',
        'closed_at',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new KoperasiScope);

        static::saving(function (ProductRequest $productRequest): void {
            $user = auth()->user();

            if (! $user) {
                return;
            }

            if ($user->isSystemOwner()) {
                if (! $productRequest->exists || $productRequest->isDirty('koperasi_id')) {
                    throw new AuthorizationException('System owner tidak dapat membuat atau memindahkan request tenant.');
                }

                return;
            }

            if ($user->isSuperAdmin() || $user->koperasi_id === null) {
                throw new AuthorizationException('Request produk hanya dapat dibuat dari tenant aktif.');
            }

            if ($productRequest->koperasi_id === null) {
                $productRequest->koperasi_id = (int) $user->koperasi_id;
            }

            if ((int) $productRequest->koperasi_id !== (int) $user->koperasi_id) {
                throw new AuthorizationException('Request tidak berada dalam koperasi aktif Anda.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => ProductRequestType::class,
            'requester_priority' => ProductRequestPriority::class,
            'internal_priority' => ProductRequestPriority::class,
            'status' => ProductRequestStatus::class,
            'first_responded_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isAdminPrimer()) {
            $query->where($this->qualifyColumn('created_by'), $user->id);
        }

        return $query;
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProductRequestMessage::class);
    }

    public function publicMessages(): HasMany
    {
        return $this->messages()->where('visibility', 'public');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProductRequestAttachment::class);
    }

    public function initialAttachments(): HasMany
    {
        return $this->attachments()->whereNull('message_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ProductRequestStatusHistory::class);
    }
}
