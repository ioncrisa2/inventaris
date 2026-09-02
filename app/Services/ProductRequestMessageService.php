<?php

namespace App\Services;

use App\Enums\ProductRequestMessageVisibility;
use App\Enums\ProductRequestStatus;
use App\Events\ProductRequestActivity;
use App\Models\ProductRequest;
use App\Models\ProductRequestMessage;
use App\Models\User;
use App\Repositories\OwnerProductRequestRepository;
use App\Repositories\ProductRequestRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductRequestMessageService
{
    public function __construct(
        private TransactionalFileStorage $fileStorage,
        private ProductRequestAttachmentService $attachmentService,
        private ProductRequestRepository $tenantRepository,
        private OwnerProductRequestRepository $ownerRepository,
    ) {}

    /** @param array<string, mixed> $data */
    public function tenantReply(User $actor, ProductRequest $productRequest, array $data): ProductRequestMessage
    {
        Gate::forUser($actor)->authorize('reply', $productRequest);

        return $this->fileStorage->transaction(function () use ($actor, $productRequest, $data): ProductRequestMessage {
            $locked = $this->tenantRepository->findForUpdate($actor, $productRequest->id);
            Gate::forUser($actor)->authorize('reply', $locked);
            $files = array_values($data['attachments'] ?? []);
            $tokens = array_values($data['attachments_upload_uuids'] ?? []);
            $this->attachmentService->assertCapacity($locked, $files, $tokens);

            $message = $locked->messages()->create([
                'author_user_id' => $actor->id,
                'visibility' => ProductRequestMessageVisibility::Public,
                'body' => $data['body'],
            ]);
            $this->attachmentService->store(
                $locked,
                $message,
                $actor,
                $files,
            );
            $this->attachmentService->claimTokens($locked, $message, $actor, $tokens);

            $now = now();
            $locked->last_activity_at = $now;
            if ($locked->status === ProductRequestStatus::NeedsInformation) {
                $locked->statusHistories()->create([
                    'changed_by' => $actor->id,
                    'from_status' => ProductRequestStatus::NeedsInformation,
                    'to_status' => ProductRequestStatus::UnderReview,
                    'reason' => 'Informasi tambahan telah diberikan.',
                ]);
                $locked->status = ProductRequestStatus::UnderReview;
            }
            $locked->save();

            DB::afterCommit(fn () => ProductRequestActivity::dispatch(
                $locked->id,
                'tenant_replied',
                $actor->id,
            ));

            return $message;
        });
    }

    /** @param array<string, mixed> $data */
    public function ownerMessage(User $actor, ProductRequest $productRequest, array $data): ProductRequestMessage
    {
        if (! $actor->isSystemOwner()) {
            throw new AuthorizationException('Hanya system owner yang dapat menanggapi request dari workspace owner.');
        }

        return $this->fileStorage->transaction(function () use ($actor, $productRequest, $data): ProductRequestMessage {
            $locked = $this->ownerRepository->findForUpdate($productRequest->id);
            $visibility = ProductRequestMessageVisibility::from($data['visibility']);
            $files = array_values($data['attachments'] ?? []);
            $tokens = array_values($data['attachments_upload_uuids'] ?? []);
            $this->attachmentService->assertCapacity($locked, $files, $tokens);
            $message = $locked->messages()->create([
                'author_user_id' => $actor->id,
                'visibility' => $visibility,
                'body' => $data['body'],
            ]);
            $this->attachmentService->store(
                $locked,
                $message,
                $actor,
                $files,
            );
            $this->attachmentService->claimTokens($locked, $message, $actor, $tokens);

            if ($visibility === ProductRequestMessageVisibility::Public) {
                $now = now();
                $locked->first_responded_at ??= $now;
                $locked->last_activity_at = $now;

                if ($locked->status === ProductRequestStatus::Submitted) {
                    $locked->statusHistories()->create([
                        'changed_by' => $actor->id,
                        'from_status' => ProductRequestStatus::Submitted,
                        'to_status' => ProductRequestStatus::UnderReview,
                        'reason' => 'Request mulai ditinjau oleh tim produk.',
                    ]);
                    $locked->status = ProductRequestStatus::UnderReview;
                }

                $locked->save();
            }

            DB::afterCommit(fn () => ProductRequestActivity::dispatch(
                $locked->id,
                $visibility === ProductRequestMessageVisibility::Public ? 'owner_replied' : 'internal_note',
                $actor->id,
            ));

            return $message;
        });
    }
}
