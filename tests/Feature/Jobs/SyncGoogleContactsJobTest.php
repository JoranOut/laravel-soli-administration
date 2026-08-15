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

// --- failed() marks stale logs ---

test('failed marks running full-sync logs and job status as failed', function () {
    $log = \App\Models\GoogleContactSyncLog::create([
        'type' => 'full',
        'status' => 'running',
        'started_at' => now(),
    ]);
    $completed = \App\Models\GoogleContactSyncLog::create([
        'type' => 'full',
        'status' => 'completed',
        'started_at' => now()->subHour(),
        'completed_at' => now()->subHour(),
    ]);
    $jobStatus = \App\Models\JobStatus::markRunning('google-contacts-sync', 'Google Contacts Sync');

    (new SyncGoogleContactsJob)->failed(new RuntimeException('Timeout'));

    expect($log->fresh()->status)->toBe('failed');
    expect($log->fresh()->error_message)->toContain('Timeout');
    expect($log->fresh()->completed_at)->not->toBeNull();
    expect($completed->fresh()->status)->toBe('completed');
    expect($jobStatus->fresh()->status)->toBe('failed');
});

test('failed for a relatie sync only touches that relatie log', function () {
    $relatie = Relatie::factory()->create();
    $other = Relatie::factory()->create();

    $mine = \App\Models\GoogleContactSyncLog::create([
        'type' => 'relatie',
        'relatie_id' => $relatie->id,
        'status' => 'running',
        'started_at' => now(),
    ]);
    $others = \App\Models\GoogleContactSyncLog::create([
        'type' => 'relatie',
        'relatie_id' => $other->id,
        'status' => 'running',
        'started_at' => now(),
    ]);

    (new SyncGoogleContactsJob($relatie->id))->failed(null);

    expect($mine->fresh()->status)->toBe('failed');
    expect($mine->fresh()->error_message)->toContain('killed');
    expect($others->fresh()->status)->toBe('running');
});
