<?php

namespace App\Enums;

enum ProductRequestMessageVisibility: string
{
    case Public = 'public';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Balasan publik',
            self::Internal => 'Catatan internal',
        };
    }
}
