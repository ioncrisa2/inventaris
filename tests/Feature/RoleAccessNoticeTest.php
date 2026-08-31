<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin primer access and account security notices live on their relevant pages', function () {
    $adminPrimer = adminPrimerUser();

    $this->actingAs($adminPrimer)
        ->get(route('pengguna.index'))
        ->assertOk()
        ->assertSeeText('Batasan Role & Akses')
        ->assertSeeText('Anda hanya bisa melihat dan mengubah data yang terkait dengan koperasi Anda.')
        ->assertSeeText('Role Admin Primer bersifat referensi (read-only) dan tidak bisa diedit atau dihapus.')
        ->assertSeeText('Anda bisa membuat dan mengubah role custom sesuai kebutuhan.')
        ->assertSeeText('Pembuatan akun Admin Primer baru tidak tersedia melalui halaman ini.')
        ->assertSee('data-notice-key="role-access-notice.'.$adminPrimer->id.'.admin-primer-role-access"', false)
        ->assertSeeText('Jangan tampilkan lagi');

    $this->get(route('profile.show'))
        ->assertOk()
        ->assertSeeText('Keamanan Akun')
        ->assertSeeText('Jangan bagikan kata sandi Anda ke siapa pun.')
        ->assertSeeText('Segera perbarui profil jika email atau identitas akun berubah.')
        ->assertDontSeeText('Batasan Role & Akses');

    $this->get(route('panduan-singkat'))
        ->assertOk()
        ->assertDontSeeText('Jangan bagikan kata sandi Anda')
        ->assertDontSeeText('Batasan Role & Akses');
});

test('super admin notice keeps one cross tenant concern and uses consistent emphasis', function () {
    $superAdmin = superAdminUser();

    $this->actingAs($superAdmin)
        ->get(route('panduan-singkat'))
        ->assertOk()
        ->assertSeeText('Batas akses yang perlu diketahui')
        ->assertSee('<strong>baca</strong>', false)
        ->assertSee('<strong>oleh pengelola tenant</strong>', false)
        ->assertSee('<strong>Role &amp; Hak Akses</strong>', false)
        ->assertSee('<strong>status atau masa aktif</strong>', false)
        ->assertSee('<strong>filter koperasi</strong>', false)
        ->assertSee('data-notice-key="role-access-notice.'.$superAdmin->id.'.super-admin-cross-tenant"', false)
        ->assertSeeText('Jangan tampilkan lagi');

    $this->get(route('panduan-singkat.cetak'))
        ->assertOk()
        ->assertSeeText('Batas akses yang perlu diketahui')
        ->assertDontSee('data-role-access-notice', false)
        ->assertDontSeeText('Jangan tampilkan lagi');
});

test('super admin user management does not show the admin primer notice', function () {
    $this->actingAs(superAdminUser())
        ->get(route('pengguna.index'))
        ->assertOk()
        ->assertDontSeeText('Batasan Role & Akses');
});
