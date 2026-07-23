<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('application footer shows configured name version and github repository', function () {
    $this->actingAs(adminUser());

    config()->set('app.github_url', 'https://github.com/example/inventaris');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertSee('Versi '.config('app.version'))
        ->assertSee('https://github.com/example/inventaris', false)
        ->assertSee('GitHub');
});

test('application footer does not create a fake link before github is configured', function () {
    $this->actingAs(adminUser());

    config()->set('app.github_url', null);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Repository belum dihubungkan')
        ->assertDontSee('href=""', false);
});
