<?php

namespace App\Services;

use App\Models\Barang;
use App\Models\DokumenBarang;
use App\Models\DokumenKaryawan;
use App\Models\DokumenRiwayatKaryawan;
use App\Models\FotoBarang;
use App\Models\Karyawan;
use App\Models\Koperasi;
use App\Models\ProductRequestAttachment;
use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class StoredFileAccessService
{
    public function canAccess(User $user, StoredFile $file): bool
    {
        if ($user->koperasi_id === null) {
            return $user->isSystemOwner() && (int) $file->uploaded_by === (int) $user->id;
        }
        if ((int) $user->koperasi_id !== (int) $file->koperasi_id) {
            return false;
        }
        if ($file->owner_id === null) {
            return (int) $file->uploaded_by === (int) $user->id;
        }

        $file->loadMissing('owner');
        $owner = $file->owner;
        if ($owner instanceof FotoBarang || $owner instanceof DokumenBarang) {
            $owner->loadMissing('barang');
        } elseif ($owner instanceof DokumenKaryawan) {
            $owner->loadMissing('karyawan');
        } elseif ($owner instanceof DokumenRiwayatKaryawan) {
            $owner->loadMissing('riwayat.karyawan');
        } elseif ($owner instanceof ProductRequestAttachment) {
            $owner->loadMissing('productRequest');
        }

        return match (true) {
            $owner instanceof Barang => Gate::forUser($user)->allows('view', $owner),
            $owner instanceof FotoBarang => $owner->barang !== null && Gate::forUser($user)->allows('view', $owner->barang),
            $owner instanceof DokumenBarang => $owner->barang !== null && Gate::forUser($user)->allows('view', $owner->barang),
            $owner instanceof Karyawan => Gate::forUser($user)->allows('view', $owner),
            $owner instanceof DokumenKaryawan => $owner->karyawan !== null && Gate::forUser($user)->allows('view', $owner->karyawan),
            $owner instanceof DokumenRiwayatKaryawan => $owner->riwayat?->karyawan !== null
                && $user->can('karyawan.riwayat.view')
                && Gate::forUser($user)->allows('view', $owner->riwayat->karyawan),
            $owner instanceof ProductRequestAttachment => $owner->productRequest !== null
                && Gate::forUser($user)->allows('downloadAttachment', $owner->productRequest),
            $owner instanceof Koperasi => (int) $owner->id === (int) $user->koperasi_id && $user->can('pengaturan.view'),
            default => false,
        };
    }
}
