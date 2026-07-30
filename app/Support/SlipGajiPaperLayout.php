<?php

namespace App\Support;

final class SlipGajiPaperLayout
{
    public const LEFT_RIGHT = 'left_right';

    public const TOP_BOTTOM = 'top_bottom';

    public const DEFAULT = self::LEFT_RIGHT;

    public const VALUES = [
        self::LEFT_RIGHT,
        self::TOP_BOTTOM,
    ];

    public static function label(string $layout): string
    {
        return match ($layout) {
            self::TOP_BOTTOM => 'Atas–bawah',
            default => 'Kiri–kanan',
        };
    }

    public static function normalize(mixed $layout): string
    {
        return in_array($layout, self::VALUES, true)
            ? $layout
            : self::DEFAULT;
    }
}
