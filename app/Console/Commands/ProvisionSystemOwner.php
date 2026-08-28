<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\PermissionRegistrar;

class ProvisionSystemOwner extends Command
{
    protected $signature = 'system-owner:provision
        {--name= : Nama lengkap system owner}
        {--email= : Alamat email system owner}';

    protected $description = 'Membuat atau memperbarui akun global system owner secara aman';

    public function handle(): int
    {
        $name = trim((string) ($this->option('name') ?: $this->ask('Nama system owner')));
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Email system owner'))));

        $identityValidator = Validator::make(
            compact('name', 'email'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
            ],
        );

        if ($identityValidator->fails()) {
            $this->renderValidationErrors($identityValidator->errors()->all());

            return self::FAILURE;
        }

        if ($this->globalSystemOwnerRoles()->count() > 1) {
            $this->error('Provisioning dihentikan: ditemukan lebih dari satu role global system_owner untuk guard web.');

            return self::FAILURE;
        }

        $existingUser = $this->findUserByEmail($email);

        if ($existingUser?->koperasi_id !== null) {
            $this->error('Provisioning ditolak: email tersebut sudah dipakai oleh akun tenant.');

            return self::FAILURE;
        }

        if ($existingUser && ! $this->confirm(
            "Akun global dengan email {$email} sudah ada. Ganti identitas, role, dan password akun ini?",
        )) {
            $this->info('Provisioning system owner dibatalkan; tidak ada data yang diubah.');

            return self::SUCCESS;
        }

        $password = (string) $this->secret('Password baru');
        $passwordConfirmation = (string) $this->secret('Konfirmasi password baru');
        $passwordValidator = Validator::make(
            [
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            ['password' => ['required', 'confirmed', Password::defaults()]],
        );

        if ($passwordValidator->fails()) {
            $this->renderValidationErrors($passwordValidator->errors()->all());

            return self::FAILURE;
        }

        $confirmedExistingUserId = $existingUser?->getKey();

        try {
            [$user, $created] = DB::transaction(function () use (
                $confirmedExistingUserId,
                $email,
                $name,
                $password,
            ): array {
                $roles = $this->globalSystemOwnerRoles(lockForUpdate: true);

                if ($roles->count() > 1) {
                    throw new \DomainException('Ditemukan lebih dari satu role global system_owner untuk guard web.');
                }

                $role = $roles->first();

                if (! $role) {
                    $role = new Role(['name' => 'system_owner', 'guard_name' => 'web']);
                    $role->koperasi_id = null;
                    $role->save();
                }

                $user = $this->findUserByEmail($email, lockForUpdate: true);

                if ($user?->koperasi_id !== null) {
                    throw new \DomainException('Email tersebut sudah dipakai oleh akun tenant.');
                }

                if ($user && $confirmedExistingUserId === null) {
                    throw new \DomainException('Akun dibuat oleh proses lain. Jalankan ulang command untuk mengonfirmasi perubahan.');
                }

                if ($user && $user->getKey() !== $confirmedExistingUserId) {
                    throw new \DomainException('Identitas akun berubah saat provisioning. Jalankan ulang command.');
                }

                $created = ! $user;
                $user ??= new User;
                $user->forceFill([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ]);
                $user->koperasi_id = null;
                $user->unit_kerja_id = null;
                $user->save();

                // Owner hanya memiliki role owner dan tidak membawa direct
                // permission dari identitas global yang mungkin dikonversi.
                $user->syncRoles([$role]);
                $user->syncPermissions([]);

                return [$user, $created];
            }, 3);
        } catch (\DomainException $exception) {
            $this->error('Provisioning ditolak: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $action = $created ? 'dibuat' : 'diperbarui';
        $this->info("Akun system owner berhasil {$action}: {$user->email}");

        return self::SUCCESS;
    }

    /** @return Collection<int, Role> */
    private function globalSystemOwnerRoles(bool $lockForUpdate = false): Collection
    {
        $query = Role::query()
            ->where('name', 'system_owner')
            ->where('guard_name', 'web')
            ->whereNull('koperasi_id')
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    private function findUserByEmail(string $email, bool $lockForUpdate = false): ?User
    {
        $query = User::query()->whereRaw('LOWER(email) = ?', [$email]);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @param array<int, string> $messages */
    private function renderValidationErrors(array $messages): void
    {
        foreach ($messages as $message) {
            $this->error($message);
        }
    }
}
