<?php

use App\Jobs\SyncGoogleContactsJob;
use App\Models\Relatie;
use App\Services\Google\GoogleContactSyncService;

// --- Sync disabled ---

test('job does nothing when sync is disabled', function () {
    config(['services.google.contacts_sync_enabled' => false]);

    $mockService = Mockery::mock(GoogleContactSyncService::class);
    $mockService->shouldNotReceive('syncAll');
    $mockService->shouldNotReceive('syncRelatie');

    $job = new SyncGoogleContactsJob;
    $job->handle($mockService);
});

// --- Specific relatie ---

test('job calls syncRelatie for specific relatieId', function () {
    config(['services.google.contacts_sync_enabled' => true]);

    $relatie = Relatie::factory()->create();

    $mockService = Mockery::mock(GoogleContactSyncService::class);
    $mockService->shouldReceive('syncRelatie')
        ->once()
        ->withArgs(fn ($r) => $r->id === $relatie->id)
        ->andReturn(['users' => 1, 'created' => 1, 'updated' => 0, 'deleted' => 0, 'skipped' => 0]);

    $job = new SyncGoogleContactsJob($relatie->id);
    $job->handle($mockService);
});

// --- Full sync ---

test('job calls syncAll when no relatieId', function () {
    config(['services.google.contacts_sync_enabled' => true]);

    $mockService = Mockery::mock(GoogleContactSyncService::class);
    $mockService->shouldReceive('syncAll')
        ->once()
        ->andReturn(['users' => 2, 'created' => 5, 'updated' => 0, 'deleted' => 0, 'skipped' => 0]);

    $job = new SyncGoogleContactsJob;
    $job->handle($mockService);
});

// --- Nonexistent relatie ---

test('job does nothing for nonexistent relatie', function () {
    config(['services.google.contacts_sync_enabled' => true]);

    $mockService = Mockery::mock(GoogleContactSyncService::class);
    $mockService->shouldNotReceive('syncRelatie');
    $mockService->shouldNotReceive('syncAll');

    $job = new SyncGoogleContactsJob(999999);
    $job->handle($mockService);
});
