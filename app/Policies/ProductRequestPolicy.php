<?php

namespace App\Policies;

use App\Enums\ProductRequestStatus;
use App\Models\ProductRequest;
use App\Models\User;

class ProductRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isTenantActor($user) && $user->can('product-request.view');
    }

    public function view(User $user, ProductRequest $productRequest): bool
    {
        return $this->viewAny($user) && $this->belongsToVisibleScope($user, $productRequest);
    }

    public function create(User $user): bool
    {
        return $this->isTenantActor($user) && $user->can('product-request.create');
    }

    public function reply(User $user, ProductRequest $productRequest): bool
    {
        return $this->view($user, $productRequest)
            && $user->can('product-request.reply')
            && $productRequest->status->acceptsTenantReply();
    }

    public function close(User $user, ProductRequest $productRequest): bool
    {
        return $this->view($user, $productRequest)
            && $user->can('product-request.close')
            && ($productRequest->status === ProductRequestStatus::Closed
                || ! $productRequest->status->isResolved());
    }

    public function downloadAttachment(User $user, ProductRequest $productRequest): bool
    {
        return $this->view($user, $productRequest);
    }

    private function isTenantActor(User $user): bool
    {
        return $user->koperasi_id !== null
            && ! $user->isSuperAdmin()
            && ! $user->isSystemOwner();
    }

    private function belongsToVisibleScope(User $user, ProductRequest $productRequest): bool
    {
        return (int) $user->koperasi_id === (int) $productRequest->koperasi_id
            && ($user->isAdminPrimer() || (int) $productRequest->created_by === (int) $user->id);
    }
}
