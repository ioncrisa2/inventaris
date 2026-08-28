<?php

use App\Models\Koperasi;
use App\Models\PlatformAnnouncement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('system owner dapat menerbitkan pengumuman kepada semua admin primer', function () {
    $owner = systemOwnerUser();
    $adminA = adminPrimerUser(Koperasi::create(['nama' => 'Primer Pengumuman A']));
    $adminB = adminPrimerUser(Koperasi::create(['nama' => 'Primer Pengumuman B']));
    $secretBody = 'Isi pengumuman lengkap yang tidak boleh masuk payload notifikasi.';

    $this->actingAs($owner)->post(route('owner.announcements.store'), [
        'title' => 'Pemeliharaan terjadwal',
        'body' => $secretBody,
        'priority' => 'warning',
    ])->assertRedirect()->assertSessionHas('success');

    $announcement = PlatformAnnouncement::query()->firstOrFail();
    $this->patch(route('owner.announcements.publish', $announcement))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(DB::table('notifications')->whereIn('notifiable_id', [$adminA->id, $adminB->id])->count())->toBe(2)
        ->and((string) DB::table('notifications')->value('data'))->not->toContain($secretBody);
});

test('pengumuman koperasi tertentu tidak terkirim ke admin primer lain', function () {
    $owner = systemOwnerUser();
    $koperasiA = Koperasi::create(['nama' => 'Primer Target']);
    $koperasiB = Koperasi::create(['nama' => 'Primer Bukan Target']);
    $adminA = adminPrimerUser($koperasiA);
    $adminB = adminPrimerUser($koperasiB);

    $this->actingAs($owner)->post(route('owner.announcements.store'), [
        'title' => 'Khusus Primer Target',
        'body' => 'Informasi untuk satu koperasi.',
        'priority' => 'info',
        'target_koperasi_id' => $koperasiA->id,
    ])->assertRedirect();
    $announcement = PlatformAnnouncement::query()->firstOrFail();
    $this->patch(route('owner.announcements.publish', $announcement))->assertRedirect();

    expect(DB::table('notifications')->where('notifiable_id', $adminA->id)->count())->toBe(1)
        ->and(DB::table('notifications')->where('notifiable_id', $adminB->id)->count())->toBe(0);

    $this->actingAs($adminA)->get(route('announcements.show', $announcement))->assertOk();
    $this->actingAs($adminB)->get(route('announcements.show', $announcement))->assertNotFound();
});

test('non owner tidak dapat membuat atau menerbitkan pengumuman platform', function () {
    $admin = adminPrimerUser();

    $this->actingAs($admin)->post(route('owner.announcements.store'), [
        'title' => 'Tidak sah',
        'body' => 'Tidak boleh diterbitkan.',
        'priority' => 'critical',
    ])->assertForbidden();

    expect(PlatformAnnouncement::query()->count())->toBe(0);
});
