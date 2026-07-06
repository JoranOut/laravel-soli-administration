<?php

use App\Models\JobStatus;
use App\Models\Relatie;
use App\Services\Sad\SadApiClient;
use App\Services\Sad\SadSyncService;
use App\Services\MemberSyncService;
use Database\Seeders\InstrumentSoortSeeder;
use Database\Seeders\OnderdeelSeeder;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RelatieTypeSeeder::class);
    $this->seed(OnderdeelSeeder::class);
    $this->seed(InstrumentSoortSeeder::class);
});

test('full sync creates members from SAD data', function () {
    $mockClient = Mockery::mock(SadApiClient::class);

    $mockClient->shouldReceive('login')->once();

    $mockClient->shouldReceive('getActiveMembers')->once()->andReturn([
        1000 => ['lid_id' => 1000, 'onderdeel' => 'HA', 'email' => 'jan@test.nl'],
        1001 => ['lid_id' => 1001, 'onderdeel' => 'HABB', 'email' => 'piet@test.nl'],
    ]);

    $mockClient->shouldReceive('getMemberDetails')->with(1000)->andReturn([
        'voornaam' => 'Jan',
        'tussenvoegsel' => null,
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'onderdeel' => 'HA',
    ]);

    $mockClient->shouldReceive('getMemberDetails')->with(1001)->andReturn([
        'voornaam' => 'Piet',
        'tussenvoegsel' => 'van',
        'achternaam' => 'Berg',
        'email' => 'piet@test.nl',
        'onderdeel' => 'HABB',
    ]);

    $mockClient->shouldReceive('getMemberPii')->with(1000)->andReturn([
        'adres' => 'Dorpsstraat 10',
        'postcode' => '1985 AB',
        'plaats' => 'Driehuis',
        'telefoon' => '06-12345678',
        'geboortedatum' => '15-03-1990',
        'instrument' => 'Trompet',
    ]);

    $mockClient->shouldReceive('getMemberPii')->with(1001)->andReturn(null);

    $syncService = new SadSyncService($mockClient, app(MemberSyncService::class));
    $stats = $syncService->syncAll();

    expect($stats['total'])->toBe(2);
    expect($stats['created'])->toBe(2);
    expect($stats['failed'])->toBe(0);

    $jan = Relatie::where('relatie_nummer', 1000)->first();
    expect($jan)->not->toBeNull();
    expect($jan->voornaam)->toBe('Jan');
    expect($jan->geboortedatum->format('Y-m-d'))->toBe('1990-03-15');
    expect($jan->adressen()->first()->straat)->toBe('Dorpsstraat');
    expect($jan->telefoons()->first()->nummer)->toBe('06-12345678');

    $piet = Relatie::where('relatie_nummer', 1001)->first();
    expect($piet)->not->toBeNull();
    expect($piet->voornaam)->toBe('Piet');
    expect($piet->tussenvoegsel)->toBe('van');

    // JobStatus should be marked completed
    $jobStatus = JobStatus::where('name', 'sad-sync')->first();
    expect($jobStatus)->not->toBeNull();
    expect($jobStatus->status)->toBe('completed');
});

test('handles member detail failure gracefully', function () {
    $mockClient = Mockery::mock(SadApiClient::class);

    $mockClient->shouldReceive('login')->once();

    $mockClient->shouldReceive('getActiveMembers')->once()->andReturn([
        1000 => ['lid_id' => 1000, 'onderdeel' => 'HA', 'email' => 'jan@test.nl'],
        1001 => ['lid_id' => 1001, 'onderdeel' => 'HA', 'email' => 'piet@test.nl'],
    ]);

    $mockClient->shouldReceive('getMemberDetails')->with(1000)->andReturn([
        'voornaam' => 'Jan',
        'tussenvoegsel' => null,
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'onderdeel' => 'HA',
    ]);

    // Second member fails
    $mockClient->shouldReceive('getMemberDetails')->with(1001)->andReturn(null);

    $mockClient->shouldReceive('getMemberPii')->with(1000)->andReturn(null);

    $syncService = new SadSyncService($mockClient, app(MemberSyncService::class));
    $stats = $syncService->syncAll();

    expect($stats['total'])->toBe(2);
    expect($stats['created'])->toBe(1);
    expect($stats['failed'])->toBe(1);

    // Jan should exist, Piet should not
    expect(Relatie::where('relatie_nummer', 1000)->exists())->toBeTrue();
    expect(Relatie::where('relatie_nummer', 1001)->exists())->toBeFalse();

    // JobStatus should be completed_with_errors
    $jobStatus = JobStatus::where('name', 'sad-sync')->first();
    expect($jobStatus->status)->toBe('completed_with_errors');
});

test('reconciles members no longer in SAD', function () {
    // Create existing members that are NOT in the SAD response
    // Need at least 6 total so removing 1 stays under 20%
    for ($i = 2000; $i <= 2005; $i++) {
        Relatie::factory()->create(['relatie_nummer' => $i, 'actief' => true]);
    }

    $mockClient = Mockery::mock(SadApiClient::class);
    $mockClient->shouldReceive('login')->once();

    // Only members 2000-2004 are still active (2005 should be deactivated)
    $activeMembers = [];
    for ($i = 2000; $i <= 2004; $i++) {
        $activeMembers[$i] = ['lid_id' => $i, 'onderdeel' => 'HA', 'email' => "member{$i}@test.nl"];
    }
    $mockClient->shouldReceive('getActiveMembers')->once()->andReturn($activeMembers);

    // All details succeed
    for ($i = 2000; $i <= 2004; $i++) {
        $relatie = Relatie::where('relatie_nummer', $i)->first();
        $mockClient->shouldReceive('getMemberDetails')->with($i)->andReturn([
            'voornaam' => $relatie->voornaam,
            'tussenvoegsel' => $relatie->tussenvoegsel,
            'achternaam' => $relatie->achternaam,
            'email' => "member{$i}@test.nl",
            'onderdeel' => 'HA',
        ]);
        $mockClient->shouldReceive('getMemberPii')->with($i)->andReturn(null);
    }

    $syncService = new SadSyncService($mockClient, app(MemberSyncService::class));
    $stats = $syncService->syncAll();

    expect($stats['deactivated'])->toBe(1);

    $deactivated = Relatie::where('relatie_nummer', 2005)->first();
    expect($deactivated->actief)->toBeFalse();
});

test('tracks stats and job status correctly', function () {
    $mockClient = Mockery::mock(SadApiClient::class);
    $mockClient->shouldReceive('login')->once();
    $mockClient->shouldReceive('getActiveMembers')->once()->andReturn([
        1000 => ['lid_id' => 1000, 'onderdeel' => 'HA', 'email' => 'jan@test.nl'],
    ]);
    $mockClient->shouldReceive('getMemberDetails')->with(1000)->andReturn([
        'voornaam' => 'Jan',
        'tussenvoegsel' => null,
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'onderdeel' => 'HA',
    ]);
    $mockClient->shouldReceive('getMemberPii')->with(1000)->andReturn(null);

    $syncService = new SadSyncService($mockClient, app(MemberSyncService::class));
    $stats = $syncService->syncAll();

    expect($stats['total'])->toBe(1);
    expect($stats['created'])->toBe(1);
    expect($stats['updated'])->toBe(0);
    expect($stats['skipped'])->toBe(0);
    expect($stats['failed'])->toBe(0);

    $jobStatus = JobStatus::where('name', 'sad-sync')->first();
    expect($jobStatus)->not->toBeNull();
    expect($jobStatus->status)->toBe('completed');
    expect($jobStatus->metadata['total'])->toBe(1);
    expect($jobStatus->metadata['created'])->toBe(1);
});
