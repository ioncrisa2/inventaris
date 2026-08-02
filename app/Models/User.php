<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'unit_kerja_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $with = [
        'unitKerja',
    ];

    /**
     * User SENGAJA TIDAK pakai trait BelongsToKoperasi (tidak ada global
     * scope) — beda dari model tenant lain. Auth::user() me-resolve User
     * lewat query (User::find($id) di provider sesi); kalau query itu kena
     * KoperasiScope, apply()-nya butuh manggil auth()->user() buat cek
     * isSuperAdmin(), yang mana masih dalam proses di-resolve -> guard
     * belum sempat cache $this->user -> query User lagi -> rekursi tanpa
     * henti sampai kehabisan memory. Sama persis kelas masalah yang sudah
     * didokumentasikan di App\Models\Role. Listing user per-koperasi difilter
     * eksplisit lewat CurrentTenant::scopeQuery() (lihat UserRepository).
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if ($user->koperasi_id === null && auth()->check()) {
                $user->koperasi_id = auth()->user()->koperasi_id;
            }
        });
    }

    public function koperasi(): BelongsTo
    {
        return $this->belongsTo(Koperasi::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dashboard_banner_dismissed_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function unitKerja()
    {
        return $this->belongsTo(UnitKerja::class);
    }

    /**
     * super_admin bersifat global (lintas koperasi) — dipakai KoperasiScope
     * untuk bypass filter tenant. Role ini baru benar-benar di-seed di Fase 4.
     */
    public function isSuperAdmin(): bool
    {
        if ($this->koperasi_id !== null) {
            return false;
        }

        $this->loadMissing('roles');

        return $this->roles->contains(
            fn (Role $role) => $role->name === 'super_admin' && $role->koperasi_id === null,
        );
    }

    public function isAdminPrimer(): bool
    {
        if ($this->koperasi_id === null) {
            return false;
        }

        $this->loadMissing('roles');

        return $this->roles->contains(
            fn (Role $role) => $role->name === 'admin_primer'
                && (int) $role->koperasi_id === (int) $this->koperasi_id,
        );
    }

    /**
     * Inisial nama untuk ditampilkan pada avatar bulat di top bar (mis.
     * "Budi Santoso" -> "BS", satu kata -> huruf pertama saja).
     */
    public function initials(): string
    {
        $kata = preg_split('/\s+/', trim($this->name)) ?: [];

        $inisial = collect($kata)
            ->filter()
            ->take(2)
            ->map(fn ($kata) => mb_strtoupper(mb_substr($kata, 0, 1)))
            ->implode('');

        return $inisial !== '' ? $inisial : '?';
    }
}
