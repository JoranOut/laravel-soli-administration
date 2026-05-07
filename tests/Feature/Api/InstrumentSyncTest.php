<?php

use App\Models\InstrumentFamilie;
use App\Models\InstrumentSoort;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->instrumentsResponse = [
        'families' => [
            ['id' => 10, 'name' => 'Houtblazers'],
            ['id' => 20, 'name' => 'Koperblazers'],
        ],
        'soorten' => [
            ['id' => 100, 'name' => 'Klarinet', 'instrument_family_id' => 10],
            ['id' => 101, 'name' => 'Dwarsfluit', 'instrument_family_id' => 10],
            ['id' => 200, 'name' => 'Trompet', 'instrument_family_id' => 20],
        ],
    ];
});

test('syncs families and instrument types from music library', function () {
    Http::fake([
        '*/api/v1/instruments' => Http::response($this->instrumentsResponse),
    ]);

    $this->artisan('sync:instruments')
        ->expectsOutputToContain('Synced 2 families, 3 instrument types')
        ->assertSuccessful();

    expect(InstrumentFamilie::count())->toBe(2)
        ->and(InstrumentSoort::count())->toBe(3);

    $family = InstrumentFamilie::where('naam', 'Houtblazers')->first();
    expect($family)->not->toBeNull()
        ->and($family->external_id)->toBe(10);

    $type = InstrumentSoort::where('naam', 'Klarinet')->first();
    expect($type)->not->toBeNull()
        ->and($type->external_id)->toBe(100)
        ->and($type->instrument_familie_id)->toBe($family->id);
});

test('is idempotent when run twice', function () {
    Http::fake([
        '*/api/v1/instruments' => Http::response($this->instrumentsResponse),
    ]);

    $this->artisan('sync:instruments')->assertSuccessful();
    $this->artisan('sync:instruments')->assertSuccessful();

    expect(InstrumentFamilie::count())->toBe(2)
        ->and(InstrumentSoort::count())->toBe(3);
});

test('updates existing records on re-sync', function () {
    $family = InstrumentFamilie::create(['naam' => 'Koperblazers', 'external_id' => 20]);
    InstrumentSoort::create(['naam' => 'Trompet', 'instrument_familie_id' => $family->id, 'external_id' => 200]);

    Http::fake([
        '*/api/v1/instruments' => Http::response([
            'families' => [
                ['id' => 20, 'name' => 'Koperblazers'],
                ['id' => 30, 'name' => 'Slagwerk'],
            ],
            'soorten' => [
                ['id' => 200, 'name' => 'Trompet', 'instrument_family_id' => 20],
            ],
        ]),
    ]);

    $this->artisan('sync:instruments')->assertSuccessful();

    expect(InstrumentFamilie::count())->toBe(2)
        ->and(InstrumentSoort::where('naam', 'Trompet')->count())->toBe(1);
});

test('propagates rename when remote name changes', function () {
    $family = InstrumentFamilie::create(['naam' => 'Koperblazers', 'external_id' => 20]);
    $soort = InstrumentSoort::create(['naam' => 'Sousafoon', 'instrument_familie_id' => $family->id, 'external_id' => 200]);

    Http::fake([
        '*/api/v1/instruments' => Http::response([
            'families' => [
                ['id' => 20, 'name' => 'Koper'],
            ],
            'soorten' => [
                ['id' => 200, 'name' => 'Sousafoooon', 'instrument_family_id' => 20],
            ],
        ]),
    ]);

    $this->artisan('sync:instruments')->assertSuccessful();

    expect($family->fresh()->naam)->toBe('Koper')
        ->and($soort->fresh()->naam)->toBe('Sousafoooon')
        ->and(InstrumentFamilie::count())->toBe(1)
        ->and(InstrumentSoort::count())->toBe(1);
});

test('handles API failure gracefully', function () {
    Http::fake([
        '*/api/v1/instruments' => Http::response('Internal Server Error', 500),
    ]);

    $this->artisan('sync:instruments')
        ->expectsOutputToContain('Instrument sync failed')
        ->assertFailed();

    expect(InstrumentFamilie::count())->toBe(0);
});

test('skips soorten with unknown family', function () {
    Http::fake([
        '*/api/v1/instruments' => Http::response([
            'families' => [
                ['id' => 10, 'name' => 'Houtblazers'],
            ],
            'soorten' => [
                ['id' => 100, 'name' => 'Klarinet', 'instrument_family_id' => 10],
                ['id' => 999, 'name' => 'Onbekend', 'instrument_family_id' => 99],
            ],
        ]),
    ]);

    $this->artisan('sync:instruments')->assertSuccessful();

    expect(InstrumentSoort::count())->toBe(1)
        ->and(InstrumentSoort::where('naam', 'Klarinet')->exists())->toBeTrue()
        ->and(InstrumentSoort::where('naam', 'Onbekend')->exists())->toBeFalse();
});

test('returns empty result when music library has no data', function () {
    Http::fake([
        '*/api/v1/instruments' => Http::response(['families' => [], 'soorten' => []]),
    ]);

    $this->artisan('sync:instruments')
        ->expectsOutputToContain('Synced 0 families, 0 instrument types')
        ->assertSuccessful();
});
