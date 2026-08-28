<?php

namespace App\Enums;

enum ProductRequestType: string
{
    case Feature = 'feature';
    case Bug = 'bug';
    case Support = 'support';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Feature => 'Permintaan fitur',
            self::Bug => 'Laporan kendala',
            self::Support => 'Bantuan penggunaan',
            self::Other => 'Masukan lainnya',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type) => [$type->value => $type->label()])
            ->all();
    }
}
