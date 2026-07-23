<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cache permission Spatie bisa menyimpan role/permission dari test
        // sebelumnya yang datanya sudah di-rollback oleh RefreshDatabase,
        // jadi cache-nya perlu dikosongkan di setiap awal test.
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
