<?php

namespace App\Notifications;

use App\Models\ProductRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductRequestUpdated extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ProductRequest $productRequest,
        private readonly string $event,
        private readonly bool $ownerRoute,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'ticket_number' => $this->productRequest->ticket_number,
            'status' => $this->productRequest->status->value,
            'status_label' => $this->productRequest->status->label(),
            'url' => $this->ownerRoute
                ? route('owner.product-requests.show', $this->productRequest->ticket_number)
                : route('product-requests.show', $this->productRequest->ticket_number),
        ];
    }

    public static function eventLabel(string $event): string
    {
        return match ($event) {
            'created' => 'Request baru diterima',
            'tenant_replied' => 'Ada balasan baru dari tenant',
            'owner_replied' => 'Tim produk memberikan balasan',
            'internal_note' => 'Catatan internal baru',
            'owner_status_changed' => 'Status request diperbarui',
            'tenant_status_changed' => 'Tenant mengubah status request',
            default => 'Request produk diperbarui',
        };
    }
}
