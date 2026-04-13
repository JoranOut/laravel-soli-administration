<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensurePassportKeysExist();
    }

    private function ensurePassportKeysExist(): void
    {
        if (! file_exists(storage_path('oauth-private.key'))) {
            Artisan::call('passport:keys', ['--no-interaction' => true]);
        }
    }
}
