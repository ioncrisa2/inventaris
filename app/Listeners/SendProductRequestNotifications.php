<?php

namespace App\Listeners;

use App\Events\ProductRequestActivity;
use App\Models\ProductRequest;
use App\Models\Scopes\KoperasiScope;
use App\Models\User;
use App\Notifications\ProductRequestUpdated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendProductRequestNotifications
{
    public function handle(ProductRequestActivity $event): void
    {
        $productRequest = ProductRequest::query()
            ->withoutGlobalScope(KoperasiScope::class)
            ->whereHas('koperasi')
            ->find($event->productRequestId);

        if (! $productRequest) {
            return;
        }

        $recipients = $this->recipients($productRequest, $event)
            ->reject(fn (User $user) => (int) $user->id === $event->actorUserId)
            ->unique('id');

        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new ProductRequestUpdated(
                    $productRequest,
                    $event->event,
                    $recipient->isSystemOwner(),
                ));
            } catch (Throwable $exception) {
                Log::warning('Notifikasi request produk gagal dikirim.', [
                    'exception' => $exception::class,
                    'event' => $event->event,
                ]);
            }
        }
    }

    /** @return Collection<int, User> */
    private function recipients(ProductRequest $productRequest, ProductRequestActivity $event): Collection
    {
        if (in_array($event->event, ['created', 'tenant_replied', 'tenant_status_changed'], true)) {
            if ($productRequest->assigned_to !== null && $event->event !== 'created') {
                return User::query()
                    ->whereKey($productRequest->assigned_to)
                    ->whereNull('koperasi_id')
                    ->whereHas('roles', fn (Builder $query) => $query
                        ->where('roles.name', 'system_owner')
                        ->whereNull('roles.koperasi_id'))
                    ->get();
            }

            return $this->ownerQuery()->get();
        }

        if ($event->event === 'internal_note') {
            return $productRequest->assigned_to !== null
                ? $this->ownerQuery()->whereKey($productRequest->assigned_to)->get()
                : $this->ownerQuery()->get();
        }

        $participantIds = $productRequest->publicMessages()
            ->whereNotNull('author_user_id')
            ->pluck('author_user_id')
            ->push($productRequest->created_by)
            ->unique();

        return User::query()
            ->where('koperasi_id', $productRequest->koperasi_id)
            ->whereIn('id', $participantIds)
            ->get();
    }

    private function ownerQuery(): Builder
    {
        return User::query()
            ->whereNull('koperasi_id')
            ->whereHas('roles', fn (Builder $query) => $query
                ->where('roles.name', 'system_owner')
                ->whereNull('roles.koperasi_id'));
    }
}
