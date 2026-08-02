<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * Factory rule validasi yang memakai batas tenant yang sama dengan query
 * aplikasi. Validation Rule bawaan menjalankan query langsung ke tabel dan
 * tidak menerima global scope Eloquent, sehingga wajib diberi koperasi_id.
 */
class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        /** @var Exists $rule */
        $rule = Rule::exists($table, $column);

        return self::forCurrentTenant($rule);
    }

    public static function existsFor(string $table, string $column, ?int $koperasiId): Exists
    {
        /** @var Exists $rule */
        $rule = Rule::exists($table, $column);

        return self::forTenant($rule, $koperasiId);
    }

    public static function unique(string $table, string $column): Unique
    {
        /** @var Unique $rule */
        $rule = Rule::unique($table, $column);

        return self::forCurrentTenant($rule);
    }

    public static function uniqueFor(string $table, string $column, ?int $koperasiId): Unique
    {
        /** @var Unique $rule */
        $rule = Rule::unique($table, $column);

        return self::forTenant($rule, $koperasiId);
    }

    /** @template T of Exists|Unique @param T $rule @return T */
    private static function forCurrentTenant(Exists|Unique $rule): Exists|Unique
    {
        $user = auth()->user();

        if (! $user || $user->isSuperAdmin()) {
            return $rule;
        }

        // Fail-closed untuk akun tenant yang rusak/tidak terikat koperasi.
        return self::forTenant($rule, CurrentTenant::id() ?? -1);
    }

    /** @template T of Exists|Unique @param T $rule @return T */
    private static function forTenant(Exists|Unique $rule, ?int $koperasiId): Exists|Unique
    {
        return $rule->where('koperasi_id', $koperasiId ?? -1);
    }
}
