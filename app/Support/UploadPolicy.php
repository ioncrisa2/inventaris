<?php

namespace App\Support;

use App\Rules\TotalUploadSize;
use App\Rules\ValidUploadFile;
use InvalidArgumentException;

final class UploadPolicy
{
    /** @return array<string, mixed> */
    public static function get(string $name): array
    {
        $policy = config("uploads.policies.{$name}");

        if (! is_array($policy)) {
            throw new InvalidArgumentException("Policy upload [{$name}] tidak ditemukan.");
        }

        return $policy;
    }

    public static function accept(string $name): string
    {
        return collect(self::get($name)['extensions'])
            ->map(fn (string $extension): string => '.'.$extension)
            ->implode(',');
    }

    /** @return list<mixed> */
    public static function fileRules(string $name, bool $required = false): array
    {
        return [
            $required ? 'required' : 'nullable',
            'file',
            new ValidUploadFile($name),
        ];
    }

    /** @return list<mixed> */
    public static function fileOrTokenRules(string $name, string $tokenField, bool $required = false): array
    {
        $rules = self::fileRules($name, false);
        $rules[0] = $required && (bool) config('uploads.features.async', false)
            ? 'required_without:'.$tokenField
            : ($required ? 'required' : 'nullable');

        return $rules;
    }

    /** @return list<mixed> */
    public static function tokenRules(string $name, bool $requiredWithoutFile = false, ?string $fileField = null): array
    {
        $rules = [
            $requiredWithoutFile && $fileField ? 'required_without:'.$fileField : 'nullable',
            'uuid',
        ];

        if (! (bool) config('uploads.features.async', false)) {
            $rules[] = 'prohibited';
        }

        return $rules;
    }

    /** @return list<mixed> */
    public static function collectionRules(string $name, bool $required = false): array
    {
        $policy = self::get($name);

        return [
            $required ? 'required' : 'nullable',
            'array',
            'max:'.(int) $policy['max_files'],
            new TotalUploadSize($name),
        ];
    }

    /** @return array<string, mixed> */
    public static function clientConfig(string $name): array
    {
        $policy = self::get($name);

        return [
            'name' => $name,
            'extensions' => array_values($policy['extensions']),
            'mimeByExtension' => $policy['mime_by_extension'],
            'maxFiles' => (int) $policy['max_files'],
            'maxFileBytes' => (int) $policy['max_file_kilobytes'] * 1024,
            'maxTotalBytes' => (int) $policy['max_total_kilobytes'] * 1024,
            'maxImageDimension' => (int) config('uploads.max_image_dimension', 8000),
            'maxImageMegapixels' => (int) config('uploads.max_image_megapixels', 40),
            'preview' => (bool) $policy['preview'],
            'camera' => (bool) $policy['camera'],
            'cropAspectRatio' => $policy['crop']['aspect_ratio'] ?? null,
            'clientMaxDimension' => $policy['client_max_dimension'] ?? null,
            'txtPreviewBytes' => (int) config('uploads.txt_preview_bytes', 204800),
        ];
    }
}
