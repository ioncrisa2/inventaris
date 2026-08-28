<?php

namespace App\Repositories;

use App\Models\ProductRequest;
use App\Models\ProductRequestAttachment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductRequestRepository
{
    /** @param array<string, mixed> $filters */
    public function paginateFor(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->visibleQuery($user)
            ->with(['creator:id,name,koperasi_id'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findFor(User $user, string $ticketNumber): ProductRequest
    {
        return $this->visibleQuery($user)
            ->where('ticket_number', $ticketNumber)
            ->with([
                'creator:id,name,koperasi_id',
                'assignedOwner:id,name',
                'duplicateOf:id,koperasi_id,ticket_number,title,status',
                'initialAttachments:id,product_request_id,message_id,original_name,mime_type,size_bytes,created_at',
                'publicMessages' => fn ($query) => $query
                    ->select(['id', 'product_request_id', 'author_user_id', 'visibility', 'body', 'created_at', 'updated_at'])
                    ->oldest(),
                'publicMessages.author:id,name,koperasi_id',
                'publicMessages.author.roles:id,name,guard_name,koperasi_id',
                'publicMessages.attachments:id,product_request_id,message_id,original_name,mime_type,size_bytes,created_at',
                'statusHistories' => fn ($query) => $query
                    ->select(['id', 'product_request_id', 'changed_by', 'from_status', 'to_status', 'reason', 'created_at'])
                    ->oldest('created_at')
                    ->oldest('id'),
                'statusHistories.actor:id,name,koperasi_id',
            ])
            ->firstOrFail();
    }

    public function findForUpdate(User $user, int $id): ProductRequest
    {
        return $this->visibleQuery($user)->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function findAttachment(ProductRequest $productRequest, int $attachmentId): ProductRequestAttachment
    {
        return $productRequest->attachments()
            ->with('message:id,product_request_id,visibility')
            ->whereKey($attachmentId)
            ->firstOrFail();
    }

    private function visibleQuery(User $user): Builder
    {
        return ProductRequest::query()
            ->where('koperasi_id', $user->koperasi_id)
            ->visibleTo($user);
    }
}
