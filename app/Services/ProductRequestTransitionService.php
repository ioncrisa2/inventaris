<?php

namespace App\Services;

use App\Enums\ProductRequestStatus;
use App\Events\ProductRequestActivity;
use App\Models\ProductRequest;
use App\Models\User;
use App\Repositories\OwnerProductRequestRepository;
use App\Repositories\ProductRequestRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductRequestTransitionService
{
    public function __construct(
        private ProductRequestRepository $tenantRepository,
        private OwnerProductRequestRepository $ownerRepository,
    ) {}

    public function toggleTenantClosed(
        User $actor,
        ProductRequest $productRequest,
        ?string $reason = null,
    ): ProductRequest {
        Gate::forUser($actor)->authorize('close', $productRequest);

        return DB::transaction(function () use ($actor, $productRequest, $reason): ProductRequest {
            $locked = $this->tenantRepository->findForUpdate($actor, $productRequest->id);
            Gate::forUser($actor)->authorize('close', $locked);
            $to = $locked->status === ProductRequestStatus::Closed
                ? ProductRequestStatus::Submitted
                : ProductRequestStatus::Closed;

            $this->applyStatus($locked, $actor, $to, $reason);
            DB::afterCommit(fn () => ProductRequestActivity::dispatch(
                $locked->id,
                'tenant_status_changed',
                $actor->id,
            ));

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function updateOwnerTriage(User $actor, ProductRequest $productRequest, array $data): ProductRequest
    {
        if (! $actor->isSystemOwner()) {
            throw new AuthorizationException('Hanya system owner yang dapat melakukan triase request.');
        }

        return DB::transaction(function () use ($actor, $productRequest, $data): ProductRequest {
            $locked = $this->ownerRepository->findForUpdate($productRequest->id);
            $to = ProductRequestStatus::from($data['status']);
            $statusChanged = $to !== $locked->status;
            $duplicateChanged = false;

            if ($statusChanged && ! in_array($to, $locked->status->allowedOwnerTransitions(), true)) {
                throw ValidationException::withMessages([
                    'status' => "Status {$locked->status->label()} tidak dapat langsung diubah menjadi {$to->label()}.",
                ]);
            }

            if (array_key_exists('assigned_to', $data)) {
                $locked->assigned_to = $this->validatedOwnerId($data['assigned_to']);
            }

            if (array_key_exists('internal_priority', $data)) {
                $locked->internal_priority = $data['internal_priority'] ?: null;
            }

            if ($to === ProductRequestStatus::Duplicate) {
                $ticket = trim((string) ($data['duplicate_ticket'] ?? ''));
                if ($ticket === '') {
                    throw ValidationException::withMessages([
                        'duplicate_ticket' => 'Pilih tiket sumber ketika menandai request sebagai duplikat.',
                    ]);
                }

                try {
                    $duplicate = $this->ownerRepository->findDuplicateCandidate($ticket, $locked->koperasi_id);
                } catch (ModelNotFoundException) {
                    throw ValidationException::withMessages([
                        'duplicate_ticket' => 'Tiket sumber tidak ditemukan dalam koperasi yang sama.',
                    ]);
                }
                $this->assertNoDuplicateCycle($locked, $duplicate);
                $duplicateChanged = (int) $locked->duplicate_of_id !== (int) $duplicate->id;
                $locked->duplicate_of_id = $duplicate->id;
            } elseif ($locked->duplicate_of_id !== null) {
                $duplicateChanged = true;
                $locked->duplicate_of_id = null;
            }

            if ($statusChanged) {
                $this->applyStatus($locked, $actor, $to, $data['reason'] ?? null, save: false);
            } elseif ($duplicateChanged) {
                $locked->last_activity_at = now();
            }

            $locked->save();

            if ($statusChanged) {
                DB::afterCommit(fn () => ProductRequestActivity::dispatch(
                    $locked->id,
                    'owner_status_changed',
                    $actor->id,
                ));
            }

            return $locked;
        }, 3);
    }

    private function applyStatus(
        ProductRequest $productRequest,
        User $actor,
        ProductRequestStatus $to,
        ?string $reason,
        bool $save = true,
    ): void {
        $from = $productRequest->status;
        $now = now();

        $productRequest->statusHistories()->create([
            'changed_by' => $actor->id,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => filled($reason) ? trim($reason) : null,
        ]);
        $productRequest->status = $to;
        $productRequest->last_activity_at = $now;
        $productRequest->closed_at = $to === ProductRequestStatus::Closed ? $now : null;

        if ($to->isResolved()) {
            $productRequest->resolved_at = $now;
        } elseif ($to !== ProductRequestStatus::Closed) {
            $productRequest->resolved_at = null;
        }

        if ($save) {
            $productRequest->save();
        }
    }

    private function validatedOwnerId(mixed $ownerId): ?int
    {
        if ($ownerId === null || $ownerId === '') {
            return null;
        }

        $owner = User::query()
            ->whereKey((int) $ownerId)
            ->whereNull('koperasi_id')
            ->whereHas('roles', fn ($query) => $query
                ->where('roles.name', 'system_owner')
                ->whereNull('roles.koperasi_id'))
            ->first();

        if (! $owner) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Penanggung jawab harus merupakan system owner yang aktif.',
            ]);
        }

        return (int) $owner->id;
    }

    private function assertNoDuplicateCycle(ProductRequest $request, ProductRequest $candidate): void
    {
        $seen = [(int) $request->id => true];
        $current = $candidate;

        while ($current !== null) {
            if (isset($seen[(int) $current->id])) {
                throw ValidationException::withMessages([
                    'duplicate_ticket' => 'Tiket sumber akan membentuk relasi duplikat melingkar.',
                ]);
            }

            $seen[(int) $current->id] = true;
            if ($current->duplicate_of_id === null) {
                break;
            }

            $current = $this->ownerRepository->findById((int) $current->duplicate_of_id);
            if ((int) $current->koperasi_id !== (int) $request->koperasi_id) {
                throw ValidationException::withMessages([
                    'duplicate_ticket' => 'Tiket sumber harus berasal dari koperasi yang sama.',
                ]);
            }
        }
    }
}
