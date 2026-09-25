<?php

use App\Jobs\SyncSadMembersJob;
use App\Models\JobStatus;
use App\Models\SadSyncLog;
use App\Services\Sad\SadSyncService;

test('job runs the sync', function () {
    $mockService = Mockery::mock(SadSyncService::class);
    $mockService->shouldReceive('syncAll')->once()->andReturn([]);

    (new SyncSadMembersJob)->handle($mockService);
});

test('it allows far longer than the 60s default and never retries', function () {
    $job = new SyncSadMembersJob;

    // Three pages per member — the default timed this out three times over
    expect($job->timeout)->toBe(1800);
    expect($job->tries)->toBe(1);
});

test('a killed job closes out its running status instead of leaving it hanging', function () {
    $status = JobStatus::markRunning('sad-sync', 'SAD Member Sync');
    $log = SadSyncLog::create(['status' => 'running', 'started_at' => now()]);

    (new SyncSadMembersJob)->failed(new Illuminate\Queue\TimeoutExceededException('has timed out'));

    expect($status->fresh()->status)->toBe('failed');
    expect($status->fresh()->last_error)->toContain('has timed out');

    expect($log->fresh()->status)->toBe('failed');
    expect($log->fresh()->completed_at)->not->toBeNull();
});

test('a killed job says so even without an exception', function () {
    $status = JobStatus::markRunning('sad-sync', 'SAD Member Sync');

    (new SyncSadMembersJob)->failed(null);

    expect($status->fresh()->last_error)->toContain('killed (timeout or worker stop)');
});

test('it leaves a completed status alone', function () {
    $status = JobStatus::markRunning('sad-sync', 'SAD Member Sync');
    $status->markCompleted(['total' => 5]);

    (new SyncSadMembersJob)->failed(null);

    expect($status->fresh()->status)->toBe('completed');
});
