<?php

use App\Models\TemplateSlipGaji;
use App\Services\SlipGajiTemplateService;
use App\Support\SlipGajiPaperLayout;
use App\Support\SlipGajiTemplateSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin dapat membuka editor slip sementara staff tanpa izin ditolak', function () {
    $this->actingAs(adminUser())
        ->get(route('pengaturan.slip-gaji.edit'))
        ->assertOk()
        ->assertSee('Editor Slip Gaji')
        ->assertSee('data-slip-template-editor', false)
        ->assertSee('Preview F4')
        ->assertSee('Pembagian default')
        ->assertSee('Kiri–kanan')
        ->assertSee('Atas–bawah');

    $this->actingAs(staffUser())
        ->get(route('pengaturan.slip-gaji.edit'))
        ->assertForbidden();
});

test('draf disimpan terpisah dan baru dipakai setelah diterbitkan', function () {
    $admin = adminUser();
    $this->actingAs($admin);
    $configuration = SlipGajiTemplateSchema::default();
    $configuration['blocks'][2]['font_size'] = 15;
    $configuration['page']['paper_layout'] = SlipGajiPaperLayout::TOP_BOTTOM;

    $this->post(route('pengaturan.slip-gaji.draft'), [
        'configuration' => json_encode($configuration),
        'expected_revision' => 1,
    ])->assertRedirect(route('pengaturan.slip-gaji.edit'));

    $template = TemplateSlipGaji::firstOrFail();
    expect($template->revisi_draf)->toBe(2)
        ->and($template->revisi_terbit)->toBeNull()
        ->and($template->konfigurasi_terbit)->toBeNull()
        ->and(app(SlipGajiTemplateService::class)->publishedConfiguration()['blocks'][2]['font_size'])->toBe(13)
        ->and(app(SlipGajiTemplateService::class)->publishedPaperLayout())->toBe(SlipGajiPaperLayout::LEFT_RIGHT);

    $this->post(route('pengaturan.slip-gaji.publish'), [
        'configuration' => json_encode($configuration),
        'expected_revision' => 2,
    ])->assertRedirect(route('pengaturan.slip-gaji.edit'));

    $template->refresh();
    expect($template->revisi_draf)->toBe(3)
        ->and($template->revisi_terbit)->toBe(3)
        ->and($template->diterbitkan_oleh)->toBe($admin->id)
        ->and($template->diterbitkan_pada)->not->toBeNull()
        ->and(app(SlipGajiTemplateService::class)->publishedConfiguration()['blocks'][2]['font_size'])->toBe(15)
        ->and(app(SlipGajiTemplateService::class)->publishedPaperLayout())->toBe(SlipGajiPaperLayout::TOP_BOTTOM);
});

test('konfigurasi lama tanpa pembagian kertas dinormalisasi ke kiri kanan', function () {
    $legacyConfiguration = SlipGajiTemplateSchema::default();
    unset($legacyConfiguration['page']['paper_layout']);

    TemplateSlipGaji::create([
        'konfigurasi_draf' => $legacyConfiguration,
        'konfigurasi_terbit' => $legacyConfiguration,
        'revisi_draf' => 1,
        'revisi_terbit' => 1,
    ]);

    $service = app(SlipGajiTemplateService::class);

    expect($service->editorState()['configuration']['page']['paper_layout'])
        ->toBe(SlipGajiPaperLayout::LEFT_RIGHT)
        ->and($service->publishedPaperLayout())
        ->toBe(SlipGajiPaperLayout::LEFT_RIGHT);
});

test('template menolak nilai pembagian kertas yang tidak dikenal', function () {
    $this->actingAs(adminUser());
    $configuration = SlipGajiTemplateSchema::default();
    $configuration['page']['paper_layout'] = 'vertical';

    $this->from(route('pengaturan.slip-gaji.edit'))
        ->post(route('pengaturan.slip-gaji.draft'), [
            'configuration' => json_encode($configuration),
            'expected_revision' => 1,
        ])
        ->assertRedirect(route('pengaturan.slip-gaji.edit'))
        ->assertSessionHasErrors('configuration');

    expect(TemplateSlipGaji::count())->toBe(0);
});

test('template menolak properti asing dan blok wajib yang hilang', function () {
    $this->actingAs(adminUser());
    $configuration = SlipGajiTemplateSchema::default();
    $configuration['blocks'][0]['raw_html'] = '<img src=x onerror=alert(1)>';

    $this->from(route('pengaturan.slip-gaji.edit'))
        ->post(route('pengaturan.slip-gaji.draft'), [
            'configuration' => json_encode($configuration),
            'expected_revision' => 1,
        ])
        ->assertRedirect(route('pengaturan.slip-gaji.edit'))
        ->assertSessionHasErrors('configuration');

    $configuration = SlipGajiTemplateSchema::default();
    $configuration['blocks'] = array_values(array_filter(
        $configuration['blocks'],
        fn (array $block) => $block['type'] !== 'payroll',
    ));

    $this->from(route('pengaturan.slip-gaji.edit'))
        ->post(route('pengaturan.slip-gaji.draft'), [
            'configuration' => json_encode($configuration),
            'expected_revision' => 1,
        ])
        ->assertRedirect(route('pengaturan.slip-gaji.edit'))
        ->assertSessionHasErrors('configuration');

    expect(TemplateSlipGaji::count())->toBe(0);
});

test('revisi lama tidak dapat menimpa draf yang lebih baru', function () {
    $this->actingAs(adminUser());
    $configuration = SlipGajiTemplateSchema::default();

    $this->post(route('pengaturan.slip-gaji.draft'), [
        'configuration' => json_encode($configuration),
        'expected_revision' => 1,
    ])->assertRedirect();

    $this->post(route('pengaturan.slip-gaji.draft'), [
        'configuration' => json_encode($configuration),
        'expected_revision' => 1,
    ])->assertStatus(409);

    expect(TemplateSlipGaji::firstOrFail()->revisi_draf)->toBe(2);
});

test('staff tidak dapat menyimpan atau menerbitkan template', function () {
    $this->actingAs(staffUser());
    $payload = [
        'configuration' => json_encode(SlipGajiTemplateSchema::default()),
        'expected_revision' => 1,
    ];

    $this->post(route('pengaturan.slip-gaji.draft'), $payload)->assertForbidden();
    $this->post(route('pengaturan.slip-gaji.publish'), $payload)->assertForbidden();

    expect(TemplateSlipGaji::count())->toBe(0);
});
