<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class SlipGajiTemplateSchema
{
    public const SCHEMA_VERSION = 1;

    public const FONT_FAMILIES = ['Arial', 'Times New Roman', 'Courier New', 'Georgia'];

    public const COLORS = ['black', 'dark', 'medium', 'light'];

    public const REQUIRED_TYPES = ['employee', 'payroll', 'signatures'];

    public const SINGLETON_TYPES = ['logo', 'organization', 'title', 'employee', 'payroll', 'signatures', 'footer'];

    public const TYPES = ['logo', 'organization', 'title', 'employee', 'payroll', 'signatures', 'footer', 'text', 'line', 'box'];

    private const BLOCK_KEYS = [
        'id', 'type', 'width', 'align', 'text_align', 'font_family', 'font_size',
        'font_weight', 'color', 'spacing_before', 'spacing_after', 'variant',
        'content', 'height',
    ];

    public static function default(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'page' => [
                'size' => 'f4',
                'orientation' => 'portrait',
                'slips_per_page' => 2,
                'paper_layout' => SlipGajiPaperLayout::DEFAULT,
            ],
            'global' => [
                'font_family' => 'Arial',
                'font_size' => 8,
                'color' => 'black',
            ],
            'blocks' => [
                self::block('logo', 'logo', 25, 'center', 'center', 8, 400, 0, 1),
                self::block('organization', 'organization', 100, 'center', 'center', 8, 700, 0, 1),
                self::block('title', 'title', 100, 'center', 'center', 13, 700, 0, 1),
                self::block('header-line', 'line', 100, 'center', 'left', 8, 400, 0, 2, 'solid'),
                self::block('employee', 'employee', 100, 'left', 'left', 8, 400, 0, 2),
                self::block('payroll', 'payroll', 100, 'left', 'left', 7.5, 400, 0, 2, 'stacked'),
                self::block('signatures', 'signatures', 100, 'center', 'center', 7, 400, 1, 1, 'three_columns'),
                self::block('footer', 'footer', 100, 'right', 'right', 6, 400, 1, 0),
            ],
        ];
    }

    public static function normalize(array $configuration): array
    {
        self::assertAllowedKeys($configuration, ['schema_version', 'page', 'global', 'blocks'], 'konfigurasi');

        if (($configuration['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            self::fail('Versi format template tidak didukung.');
        }

        $page = self::validatePage($configuration['page'] ?? null);
        $global = self::validateGlobal($configuration['global'] ?? null);
        $blocks = $configuration['blocks'] ?? null;

        if (! is_array($blocks) || ! array_is_list($blocks) || count($blocks) < 3 || count($blocks) > 30) {
            self::fail('Template harus berisi 3 sampai 30 blok.');
        }

        $normalizedBlocks = [];
        $ids = [];

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                self::fail('Blok ke-'.($index + 1).' tidak valid.');
            }

            $normalized = self::validateBlock($block, $index);

            if (in_array($normalized['id'], $ids, true)) {
                self::fail('ID blok harus unik.');
            }

            $ids[] = $normalized['id'];
            $normalizedBlocks[] = $normalized;
        }

        self::validateCardinality($normalizedBlocks);

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'page' => $page,
            'global' => $global,
            'blocks' => $normalizedBlocks,
        ];
    }

    private static function validatePage(mixed $page): array
    {
        if (! is_array($page)) {
            self::fail('Konfigurasi halaman tidak valid.');
        }

        self::assertAllowedKeys($page, ['size', 'orientation', 'slips_per_page', 'paper_layout'], 'halaman');

        if (
            ($page['size'] ?? null) !== 'f4'
            || ($page['orientation'] ?? null) !== 'portrait'
            || ($page['slips_per_page'] ?? null) !== 2
        ) {
            self::fail('Ukuran cetak dikunci ke F4 portrait dengan dua slip per lembar.');
        }

        $paperLayout = $page['paper_layout'] ?? SlipGajiPaperLayout::DEFAULT;

        if (! in_array($paperLayout, SlipGajiPaperLayout::VALUES, true)) {
            self::fail('Pembagian kertas harus kiri–kanan atau atas–bawah.');
        }

        return [
            'size' => 'f4',
            'orientation' => 'portrait',
            'slips_per_page' => 2,
            'paper_layout' => $paperLayout,
        ];
    }

    private static function validateGlobal(mixed $global): array
    {
        if (! is_array($global)) {
            self::fail('Tipografi global tidak valid.');
        }

        self::assertAllowedKeys($global, ['font_family', 'font_size', 'color'], 'tipografi global');

        return [
            'font_family' => self::enum($global['font_family'] ?? null, self::FONT_FAMILIES, 'Font global'),
            'font_size' => self::number($global['font_size'] ?? null, 6, 16, 'Ukuran font global'),
            'color' => self::enum($global['color'] ?? null, self::COLORS, 'Warna global'),
        ];
    }

    private static function validateBlock(array $block, int $index): array
    {
        self::assertAllowedKeys($block, self::BLOCK_KEYS, 'blok ke-'.($index + 1));

        $type = self::enum($block['type'] ?? null, self::TYPES, 'Tipe blok');
        $content = trim((string) ($block['content'] ?? ''));

        if (! in_array($type, ['text', 'box'], true) && $content !== '') {
            self::fail('Isi teks hanya boleh dipakai pada blok teks atau kotak.');
        }

        if (mb_strlen($content) > 300) {
            self::fail('Isi teks blok maksimal 300 karakter.');
        }

        return [
            'id' => self::blockId($block['id'] ?? null),
            'type' => $type,
            'width' => self::enum((int) ($block['width'] ?? 100), [25, 50, 75, 100], 'Lebar blok'),
            'align' => self::enum($block['align'] ?? null, ['left', 'center', 'right'], 'Posisi blok'),
            'text_align' => self::enum($block['text_align'] ?? null, ['left', 'center', 'right'], 'Perataan teks'),
            'font_family' => self::enum($block['font_family'] ?? null, self::FONT_FAMILIES, 'Font blok'),
            'font_size' => self::number($block['font_size'] ?? null, 6, 16, 'Ukuran font blok'),
            'font_weight' => self::enum((int) ($block['font_weight'] ?? 400), [400, 700], 'Ketebalan font'),
            'color' => self::enum($block['color'] ?? null, self::COLORS, 'Warna blok'),
            'spacing_before' => self::number($block['spacing_before'] ?? null, 0, 8, 'Jarak atas'),
            'spacing_after' => self::number($block['spacing_after'] ?? null, 0, 8, 'Jarak bawah'),
            'variant' => self::variant($type, $block['variant'] ?? 'default'),
            'content' => $content,
            'height' => self::number($block['height'] ?? 8, 2, 30, 'Tinggi blok'),
        ];
    }

    private static function validateCardinality(array $blocks): void
    {
        $counts = collect($blocks)->countBy('type');

        foreach (self::REQUIRED_TYPES as $type) {
            if (($counts[$type] ?? 0) !== 1) {
                self::fail('Blok wajib employee, payroll, dan signatures harus masing-masing tepat satu.');
            }
        }

        foreach (self::SINGLETON_TYPES as $type) {
            if (($counts[$type] ?? 0) > 1) {
                self::fail('Blok '.$type.' tidak boleh diduplikasi.');
            }
        }

        foreach (['text' => 8, 'line' => 8, 'box' => 4] as $type => $maximum) {
            if (($counts[$type] ?? 0) > $maximum) {
                self::fail('Jumlah blok '.$type.' melebihi batas.');
            }
        }
    }

    private static function variant(string $type, mixed $variant): string
    {
        $allowed = match ($type) {
            'payroll' => ['stacked', 'columns'],
            'signatures' => ['three_columns', 'two_plus_one'],
            'line', 'box' => ['solid', 'dashed'],
            default => ['default'],
        };

        return self::enum($variant, $allowed, 'Pola blok');
    }

    private static function block(
        string $id,
        string $type,
        int $width,
        string $align,
        string $textAlign,
        float $fontSize,
        int $fontWeight,
        float $spacingBefore,
        float $spacingAfter,
        string $variant = 'default',
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'width' => $width,
            'align' => $align,
            'text_align' => $textAlign,
            'font_family' => 'Arial',
            'font_size' => fmod($fontSize, 1.0) === 0.0 ? (int) $fontSize : $fontSize,
            'font_weight' => $fontWeight,
            'color' => 'black',
            'spacing_before' => fmod($spacingBefore, 1.0) === 0.0 ? (int) $spacingBefore : $spacingBefore,
            'spacing_after' => fmod($spacingAfter, 1.0) === 0.0 ? (int) $spacingAfter : $spacingAfter,
            'variant' => $variant,
            'content' => '',
            'height' => 8,
        ];
    }

    private static function assertAllowedKeys(array $value, array $allowed, string $label): void
    {
        $unknown = array_diff(array_keys($value), $allowed);

        if ($unknown !== []) {
            self::fail('Properti tidak dikenal pada '.$label.': '.implode(', ', $unknown).'.');
        }
    }

    private static function blockId(mixed $value): string
    {
        if (! is_string($value) || ! preg_match('/^[a-z][a-z0-9-]{1,49}$/', $value)) {
            self::fail('ID blok tidak valid.');
        }

        return $value;
    }

    private static function enum(mixed $value, array $allowed, string $label): mixed
    {
        if (! in_array($value, $allowed, true)) {
            self::fail($label.' tidak valid.');
        }

        return $value;
    }

    private static function number(mixed $value, float $minimum, float $maximum, string $label): float|int
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            self::fail($label.' tidak valid.');
        }

        $number = (float) $value;

        if ($number < $minimum || $number > $maximum) {
            self::fail($label.' harus antara '.$minimum.' dan '.$maximum.'.');
        }

        return fmod($number, 1.0) === 0.0 ? (int) $number : round($number, 1);
    }

    private static function fail(string $message): never
    {
        throw ValidationException::withMessages(['configuration' => $message]);
    }
}
