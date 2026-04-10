<?php

use App\Jobs\SyncGoogleContactsJob;
use App\Models\Relatie;
use App\Services\Google\GoogleContactSyncService;
use Illuminate\Support\Facades\Bus;

// --- Disabled config ---

test('command fails when sync is disabled', function () {
    config(['services.google.contacts_sync_enabled' => false]);

    $this->artisan('sync:google-contacts')
        ->assertFailed()
        ->expectsOutputToContain('Google Contacts sync is disabled');
});

// --- Dispatch ---

test('command dispatches job by default', function () {
    config(['services.google.contacts_sync_enabled' => true]);
    Bus::fake();

    $this->artisan('sync:google-contacts')
        ->assertSuccessful();

    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === null);
});

test('command with --relatie dispatches job for specific relatie', function () {
    config(['services.google.contacts_sync_enabled' => true]);
    Bus::fake();

    $relatie = Relatie::factory()->create();

    $this->artisan('sync:google-contacts', ['--relatie' => $relatie->id])
        ->assertSuccessful();

    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === $relatie->id);
});

// --- Direct execution ---

test('command with --dry-run runs directly with dryRun flag', function () {
    config(['services.google.contacts_sync_enabled' => true]);

    $mockService = Mockery::mock(GoogleContactSyncService::class);
    $mockService->shouldReceive('syncAll')
        ->with(true)
        ->once()
        ->andReturn(['users' => 0, 'created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0]);
    $this->app->instance(GoogleContactSyncService::class, $mockService);

    $this->artisan('sync:google-contacts', ['--dry-run' => true])
        ->assertSuccessful();
});

test('command with --sync runs directly', function () {
    config(['services.google.contacts_sync_enabled' => true]);

    $mockService = Mockery::mock(GoogleContactSyncService::class);
    $mockService->shouldReceive('syncAll')
        ->with(false)
        ->once()
        ->andReturn(['users' => 2, 'created' => 5, 'updated' => 1, 'deleted' => 0, 'skipped' => 3]);
    $this->app->instance(GoogleContactSyncService::class, $mockService);

    $this->artisan('sync:google-contacts', ['--sync' => true])
        ->assertSuccessful();
});
