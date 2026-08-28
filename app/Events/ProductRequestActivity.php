<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ProductRequestActivity
{
    use Dispatchable;

    public function __construct(
        public readonly int $productRequestId,
        public readonly string $event,
        public readonly int $actorUserId,
    ) {}
}
