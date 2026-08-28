<?php

namespace App\Services;

use App\Models\Karyawan;
use App\Models\KaryawanAccountAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KaryawanAccountService
{
    /** @return Collection<int, User> */
    public function availableUsers(Karyawan $karyawan): Collection
    {
        return User::query()
            ->where('koperasi_id', $karyawan->koperasi_id)
            ->where(function ($query) use ($karyawan) {
                $query->whereDoesntHave('karyawan');

                if ($karyawan->user_id !== null) {
                    $query->orWhere('users.id', $karyawan->user_id);
                }
            })
            ->with('roles:id,name,koperasi_id')
            ->orderBy('name')
            ->get();
    }

    public function link(User $actor, Karyawan $karyawan, User $target): Karyawan
    {
        $this->ensureActorCanManage($actor, $karyawan);

        return DB::transaction(function () use ($actor, $karyawan, $target) {
            $lockedKaryawan = Karyawan::query()->whereKey($karyawan->getKey())->lockForUpdate()->firstOrFail();
            $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if ((int) $lockedKaryawan->user_id === (int) $lockedTarget->getKey()) {
                return $lockedKaryawan;
            }

            if ($lockedKaryawan->user_id !== null) {
                throw ValidationException::withMessages([
                    'user_id' => 'Karyawan sudah terhubung dengan akun lain. Lepas hubungan lama terlebih dahulu.',
                ]);
            }

            $this->ensureTargetCanBeLinked($lockedKaryawan, $lockedTarget);

            $alreadyLinked = Karyawan::query()
                ->where('user_id', $lockedTarget->getKey())
                ->whereKeyNot($lockedKaryawan->getKey())
                ->exists();

            if ($alreadyLinked) {
                throw ValidationException::withMessages([
                    'user_id' => 'Akun tersebut sudah terhubung dengan karyawan lain.',
                ]);
            }

            $lockedKaryawan->user_id = $lockedTarget->getKey();
            $lockedKaryawan->save();

            if ((int) $lockedTarget->unit_kerja_id !== (int) $lockedKaryawan->unit_kerja_id) {
                $lockedTarget->unit_kerja_id = $lockedKaryawan->unit_kerja_id;
                $lockedTarget->save();
            }

            KaryawanAccountAuditLog::query()->create([
                'actor_user_id' => $actor->getKey(),
                'karyawan_id' => $lockedKaryawan->getKey(),
                'target_user_id' => $lockedTarget->getKey(),
                'action' => 'linked',
            ]);

            return $lockedKaryawan;
        }, 3);
    }

    public function unlink(User $actor, Karyawan $karyawan): Karyawan
    {
        $this->ensureActorCanManage($actor, $karyawan);

        return DB::transaction(function () use ($actor, $karyawan) {
            $lockedKaryawan = Karyawan::query()->whereKey($karyawan->getKey())->lockForUpdate()->firstOrFail();
            $targetUserId = $lockedKaryawan->user_id;

            if ($targetUserId === null) {
                return $lockedKaryawan;
            }

            $lockedKaryawan->user_id = null;
            $lockedKaryawan->save();

            KaryawanAccountAuditLog::query()->create([
                'actor_user_id' => $actor->getKey(),
                'karyawan_id' => $lockedKaryawan->getKey(),
                'target_user_id' => $targetUserId,
                'action' => 'unlinked',
            ]);

            return $lockedKaryawan;
        }, 3);
    }

    private function ensureActorCanManage(User $actor, Karyawan $karyawan): void
    {
        if (! $actor->can('karyawan.akun.update')
            || $actor->koperasi_id === null
            || (int) $actor->koperasi_id !== (int) $karyawan->koperasi_id) {
            throw new \DomainException('Anda tidak memiliki izin untuk mengelola akun karyawan ini.');
        }
    }

    private function ensureTargetCanBeLinked(Karyawan $karyawan, User $target): void
    {
        if ($target->koperasi_id === null || $target->isPlatformAccount()) {
            throw ValidationException::withMessages([
                'user_id' => 'Akun platform tidak dapat dihubungkan dengan karyawan tenant.',
            ]);
        }

        if ((int) $target->koperasi_id !== (int) $karyawan->koperasi_id) {
            throw ValidationException::withMessages([
                'user_id' => 'Akun dan karyawan harus berasal dari koperasi yang sama.',
            ]);
        }
    }
}
