<?php

namespace App\Repositories;

use App\Models\Koperasi;
use App\Models\ProductRequest;
use App\Models\ProductRequestAttachment;
use App\Models\Scopes\KoperasiScope;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OwnerProductRequestRepository
{
    /** @param array<string, mixed> $filters */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->filteredQuery($filters)
            ->with([
                'koperasi:id,nama',
                'creator:id,name,koperasi_id',
                'assignedOwner:id,name',
            ])
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(string $ticketNumber): ProductRequest
    {
        return $this->baseQuery()
            ->where('ticket_number', $ticketNumber)
            ->with([
                'koperasi:id,nama',
                'creator:id,name,koperasi_id',
                'assignedOwner:id,name',
                'duplicateOf' => fn ($query) => $query
                    ->withoutGlobalScope(KoperasiScope::class)
                    ->select(['id', 'koperasi_id', 'ticket_number', 'title', 'status']),
                'initialAttachments:id,product_request_id,message_id,original_name,mime_type,size_bytes,created_at',
                'initialAttachments.storedFiles:id,uuid,owner_type,owner_id,status,scan_status,failure_code',
                'messages' => fn ($query) => $query
                    ->select(['id', 'product_request_id', 'author_user_id', 'visibility', 'body', 'created_at', 'updated_at'])
                    ->oldest(),
                'messages.author:id,name,koperasi_id',
                'messages.author.roles:id,name,guard_name,koperasi_id',
                'messages.attachments:id,product_request_id,message_id,original_name,mime_type,size_bytes,created_at',
                'messages.attachments.storedFiles:id,uuid,owner_type,owner_id,status,scan_status,failure_code',
                'statusHistories' => fn ($query) => $query
                    ->select(['id', 'product_request_id', 'changed_by', 'from_status', 'to_status', 'reason', 'created_at'])
                    ->oldest('created_at')
                    ->oldest('id'),
                'statusHistories.actor:id,name,koperasi_id',
            ])
            ->firstOrFail();
    }

    public function findForUpdate(int $id): ProductRequest
    {
        return $this->baseQuery()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    public function findById(int $id): ProductRequest
    {
        return $this->baseQuery()->whereKey($id)->firstOrFail();
    }

    public function findDuplicateCandidate(string $ticketNumber, int $koperasiId): ProductRequest
    {
        return $this->baseQuery()
            ->where('koperasi_id', $koperasiId)
            ->where('ticket_number', $ticketNumber)
            ->firstOrFail();
    }

    public function findAttachment(ProductRequest $productRequest, int $attachmentId): ProductRequestAttachment
    {
        return $productRequest->attachments()
            ->with('message:id,product_request_id,visibility')
            ->whereKey($attachmentId)
            ->firstOrFail();
    }

    /** @return Collection<int, Koperasi> */
    public function koperasis(): Collection
    {
        return Koperasi::query()
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('product_requests')
                ->whereColumn('product_requests.koperasi_id', 'koperasi.id'))
            ->orderBy('nama')
            ->get(['id', 'nama']);
    }

    /** @return Collection<int, User> */
    public function owners(): Collection
    {
        return User::query()
            ->whereNull('koperasi_id')
            ->whereHas('roles', fn ($query) => $query
                ->where('roles.name', 'system_owner')
                ->whereNull('roles.koperasi_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @param array<string, mixed> $filters
     * @return array<string, int|float|null>
     */
    public function statistics(array $filters = []): array
    {
        $query = $this->filteredQuery($filters);
        $counts = (clone $query)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $activeStatuses = ['submitted', 'under_review', 'needs_information', 'considered', 'planned', 'in_progress'];

        return [
            'total' => (int) $counts->sum(),
            'backlog' => (int) $counts->only($activeStatuses)->sum(),
            'submitted' => (int) ($counts['submitted'] ?? 0),
            'needs_information' => (int) ($counts['needs_information'] ?? 0),
            'planned_or_in_progress' => (int) $counts->only(['planned', 'in_progress'])->sum(),
            'average_first_response_minutes' => $this->averageMinutes((clone $query)->whereNotNull('first_responded_at'), 'created_at', 'first_responded_at'),
            'average_resolution_minutes' => $this->averageMinutes((clone $query)->whereNotNull('resolved_at'), 'created_at', 'resolved_at'),
        ];
    }

    private function baseQuery(): Builder
    {
        return ProductRequest::query()
            ->withoutGlobalScope(KoperasiScope::class)
            ->whereHas('koperasi');
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters): Builder
    {
        return $this->baseQuery()
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($filters['koperasi_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('koperasi_id', $id))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('internal_priority', $priority))
            ->when($filters['assigned_to'] ?? null, fn (Builder $query, int|string $id) => $query->where('assigned_to', $id))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function averageMinutes(Builder $query, string $from, string $to): ?float
    {
        $driver = $query->getConnection()->getDriverName();
        $expression = match ($driver) {
            'sqlite' => "AVG((julianday({$to}) - julianday({$from})) * 1440.0)",
            'pgsql' => "AVG(EXTRACT(EPOCH FROM ({$to} - {$from})) / 60.0)",
            'mysql', 'mariadb' => "AVG(TIMESTAMPDIFF(SECOND, {$from}, {$to}) / 60.0)",
            'sqlsrv' => "AVG(DATEDIFF(SECOND, {$from}, {$to}) / 60.0)",
            default => null,
        };

        if ($expression === null) {
            return null;
        }

        $value = (clone $query)->selectRaw("{$expression} as aggregate")->value('aggregate');

        return $value === null ? null : round((float) $value, 1);
    }
}
