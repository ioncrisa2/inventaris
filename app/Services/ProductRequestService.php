<?php

namespace App\Services;

use App\Enums\ProductRequestStatus;
use App\Events\ProductRequestActivity;
use App\Models\ProductRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProductRequestService
{
    public function __construct(
        private TransactionalFileStorage $fileStorage,
        private ProductRequestAttachmentService $attachmentService,
        private ProductRequestTransitionService $transitionService,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): ProductRequest
    {
        Gate::forUser($actor)->authorize('create', ProductRequest::class);

        return $this->fileStorage->transaction(function () use ($actor, $data): ProductRequest {
            $now = now();
            $productRequest = ProductRequest::query()->create([
                'ticket_number' => 'TMP-'.Str::lower(Str::random(20)),
                'koperasi_id' => $actor->koperasi_id,
                'created_by' => $actor->id,
                'type' => $data['type'],
                'module' => $data['module'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'],
                'requester_priority' => $data['requester_priority'],
                'status' => ProductRequestStatus::Submitted,
                'last_activity_at' => $now,
            ]);

            // Auto-increment ID adalah sequence database yang concurrency-safe.
            // Nomor sementara mencegah window NULL pada kolom unique.
            $productRequest->ticket_number = sprintf(
                'REQ-%s-%06d',
                $productRequest->created_at->format('Y'),
                $productRequest->id,
            );
            $productRequest->save();

            $productRequest->statusHistories()->create([
                'changed_by' => $actor->id,
                'from_status' => null,
                'to_status' => ProductRequestStatus::Submitted,
            ]);

            $files = array_values($data['attachments'] ?? []);
            $tokens = array_values($data['attachments_upload_uuids'] ?? []);
            $this->attachmentService->assertCapacity($productRequest, $files, $tokens);
            $this->attachmentService->store(
                $productRequest,
                null,
                $actor,
                $files,
            );
            $this->attachmentService->claimTokens(
                $productRequest,
                null,
                $actor,
                $tokens,
            );

            DB::afterCommit(fn () => ProductRequestActivity::dispatch(
                $productRequest->id,
                'created',
                $actor->id,
            ));

            return $productRequest;
        });
    }

    /** @param array<string, mixed> $data */
    public function toggleClosed(User $actor, ProductRequest $productRequest, array $data): ProductRequest
    {
        return $this->transitionService->toggleTenantClosed(
            $actor,
            $productRequest,
            $data['reason'] ?? null,
        );
    }
}
