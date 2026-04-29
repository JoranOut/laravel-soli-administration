<?php

use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieInstrument;
use Database\Seeders\InstrumentSoortSeeder;
use Database\Seeders\OnderdeelSeeder;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RelatieTypeSeeder::class);
    $this->seed(OnderdeelSeeder::class);
    $this->seed(InstrumentSoortSeeder::class);
    $this->fixturePath = base_path('tests/fixtures/sad-members-sample.json');
});

test('import creates relaties from JSON', function () {
    $this->artisan('import:sad-members', ['path' => $this->fixturePath])
        ->assertExitCode(0);

    expect(Relatie::count())->toBe(6);

    $relatie9001 = Relatie::where('relatie_nummer', 9001)->first();
    $relatie9002 = Relatie::where('relatie_nummer', 9002)->first();
    $relatie9003 = Relatie::where('relatie_nummer', 9003)->first();
    $relatie9004 = Relatie::where('relatie_nummer', 9004)->first();
    $relatie9005 = Relatie::where('relatie_nummer', 9005)->first();
    $relatie9006 = Relatie::where('relatie_nummer', 9006)->first();

    expect($relatie9001)->not->toBeNull();
    expect($relatie9002)->not->toBeNull();
    expect($relatie9003)->not->toBeNull();
    expect($relatie9004)->not->toBeNull();
    expect($relatie9005)->not->toBeNull();
    expect($relatie9006)->not->toBeNull();
});

test('import parses personal info correctly', function () {
    $this->artisan('import:sad-members', ['path' => $this->fixturePath]);

    $relatie = Relatie::where('relatie_nummer', 9001)->first();

    expect($relatie->voornaam)->toBe('Test');
    expect($relatie->achternaam)->toBe('Lid');
    expect($relatie->geboortedatum->format('Y-m-d'))->toBe('1990-03-15');
    expect($relatie->actief)->toBeTrue();
});

test('import creates address, email, phone', function () {
    $this->artisan('import:sad-members', ['path' => $this->fixturePath]);

    $relatie = Relatie::where('relatie_nummer', 9001)->first();

    // Address — "Dorpsstraat 10" should split into straat + huisnummer
    $adres = $relatie->adressen()->first();
    expect($adres)->not->toBeNull();
    expect($adres->straat)->toBe('Dorpsstraat');
    expect($adres->huisnummer)->toBe('10');
    expect($adres->postcode)->toBe('1985 AA');
    expect($adres->plaats)->toBe('Driehuis');

    // Email
    $email = $relatie->emails()->first();
    expect($email)->not->toBeNull();
    expect($email->email)->toBe('testlid@example.com');

    // Phone
    $telefoon = $relatie->telefoons()->first();
    expect($telefoon)->not->toBeNull();
    expect($telefoon->nummer)->toBe('0612345678');
});

test('import creates lidmaatschap records', function () {
    $this->artisan('import:sad-members', ['path' => $this->fixturePath]);

    // Active member: open period
    $relatie9001 = Relatie::where('relatie_nummer', 9001)->first();
    $sinds9001 = $relatie9001->relatieSinds()->first();
    expect($sinds9001)->not->toBeNull();
    expect($sinds9001->lid_sinds->format('Y-m-d'))->toBe('2010-01-01');
    expect($sinds9001->lid_tot)->toBeNull();

    // Ex-member: closed period
    $relatie9002 = Relatie::where('relatie_nummer', 9002)->first();
    $sinds9002 = $relatie9002->relatieSinds()->first();
    expect($sinds9002)->not->toBeNull();
    expect($sinds9002->lid_sinds->format('Y-m-d'))->toBe('2000-09-01');
    expect($sinds9002->lid_tot->format('Y-m-d'))->toBe('2015-06-30');
});

test('import decomposes combined onderdeel codes', function () {
    $this->artisan('import:sad-members', ['path' => $this->fixturePath]);

    $relatie = Relatie::where('relatie_nummer', 9003)->first();
    $onderdeelAfkortingen = $relatie->onderdelen->pluck('afkorting')->sort()->values()->all();

    expect($onderdeelAfkortingen)->toBe(['HA', 'KO']);
});

test('import assigns latest instrument per onderdeel', function () {
    $this->artisan('import:sad-members', ['path' => $this->fixturePath]);

    $relatie = Relatie::where('relatie_nummer', 9004)->first();
    $ha = Onderdeel::where('afkorting', 'HA')->first();
    $ko = Onderdeel::where('afkorting', 'KO')->first();

    $instruments = $relatie->relatieInstrumenten()->with('instrumentSoort')->get();

    // Harmonie should have Bariton (latest overlapping, van=2009)
    $haInstrument = $instruments->where('onderdeel_id', $ha->id)->first();
    expect($haInstrument)->not->toBeNull();
    expect($haInstrument->instrumentSoort->naam)->toBe('Bariton');

    // Klein Orkest should have Bariton (only overlapping instrument)
    $koInstrument = $instruments->where('onderdeel_id', $ko->id)->first();
    expect($koInstrument)->not->toBeNull();
    expect($koInstrument->instrumentSoort->naam)->toBe('Bariton');

    // Should NOT have Trompet on any onderdeel
    $trompetRecords = $instruments->filter(fn ($ri) => $ri->instrumentSoort->naam === 'Trompet');
    expect($trompetRecords)->toBeEmpty();
});

test('import fixes mojibake in names', function () {
    $this->artisan('import:sad-members', ['path' => $this->fixturePath]);

    $relatie = Relatie::where('relatie_nummer', 9005)->first();

    // Raw fixture has "MagrÃ©" (double-encoded), should become "Magré"
    expect($relatie->achternaam)->toBe('Magré');
});

test('import closes onderdelen for ex-members', function () {
    $this->artisan('import:sad-members', ['path' => $this->fixturePath]);

    $relatie = Relatie::where('relatie_nummer', 9002)->first();
    $pivot = $relatie->onderdelen()->first()->pivot;

    // Onderdeel tot should match lidmaatschap tot (2015-06-30)
    expect($pivot->tot)->toBe('2015-06-30');
});

test('import is idempotent', function () {
    // Run import twice
    $this->artisan('import:sad-members', ['path' => $this->fixturePath])
        ->assertExitCode(0);
    $this->artisan('import:sad-members', ['path' => $this->fixturePath])
        ->assertExitCode(0);

    // Should still have exactly 6 relaties (no duplicates)
    expect(Relatie::count())->toBe(6);
});

test('fresh flag clears existing data', function () {
    // Pre-create a relatie that is NOT in the fixture
    Relatie::create([
        'relatie_nummer' => 8888,
        'voornaam' => 'Pre',
        'achternaam' => 'Existing',
        'actief' => true,
    ]);
    expect(Relatie::count())->toBe(1);

    // Run import with --fresh
    $this->artisan('import:sad-members', [
        'path' => $this->fixturePath,
        '--fresh' => true,
    ])->assertExitCode(0);

    // Original relatie should be gone, only fixture relaties exist
    expect(Relatie::where('relatie_nummer', 8888)->exists())->toBeFalse();
    expect(Relatie::count())->toBe(6);
});

test('re-import does not reassign manually removed lid type', function () {
    // First import: dirigent (9006) gets lid type from lidmaatschap periods
    $this->artisan('import:sad-members', ['path' => $this->fixturePath])
        ->assertExitCode(0);

    $relatie = Relatie::where('relatie_nummer', 9006)->first();
    $lidTypeId = DB::table('soli_relatie_types')->where('naam', 'lid')->value('id');

    expect(
        DB::table('soli_relatie_relatie_type')
            ->where('relatie_id', $relatie->id)
            ->where('relatie_type_id', $lidTypeId)
            ->exists()
    )->toBeTrue();

    // Manually remove lid type (as an admin would for a dirigent who is not a lid)
    DB::table('soli_relatie_relatie_type')
        ->where('relatie_id', $relatie->id)
        ->where('relatie_type_id', $lidTypeId)
        ->delete();

    expect(
        DB::table('soli_relatie_relatie_type')
            ->where('relatie_id', $relatie->id)
            ->where('relatie_type_id', $lidTypeId)
            ->exists()
    )->toBeFalse();

    // Re-import: lid type should NOT be reassigned to matched relatie
    $this->artisan('import:sad-members', ['path' => $this->fixturePath])
        ->assertExitCode(0);

    expect(
        DB::table('soli_relatie_relatie_type')
            ->where('relatie_id', $relatie->id)
            ->where('relatie_type_id', $lidTypeId)
            ->exists()
    )->toBeFalse();
});

test('import skips relaties with beheerd_in_admin flag', function () {
    // Pre-create a relatie with beheerd_in_admin that matches a fixture lid_id
    $relatie = Relatie::create([
        'relatie_nummer' => 9001,
        'voornaam' => 'Admin',
        'achternaam' => 'Managed',
        'actief' => true,
        'beheerd_in_admin' => true,
    ]);

    $this->artisan('import:sad-members', ['path' => $this->fixturePath])
        ->assertExitCode(0);

    // Relatie should NOT have been updated with SAD data
    $relatie->refresh();
    expect($relatie->voornaam)->toBe('Admin');
    expect($relatie->achternaam)->toBe('Managed');

    // No sub-resources should have been added
    expect($relatie->adressen()->count())->toBe(0);
    expect($relatie->emails()->count())->toBe(0);
    expect($relatie->telefoons()->count())->toBe(0);
    expect($relatie->relatieSinds()->count())->toBe(0);
});

test('import updates relaties without beheerd_in_admin flag', function () {
    // Pre-create a relatie WITHOUT beheerd_in_admin that matches a fixture lid_id
    $relatie = Relatie::create([
        'relatie_nummer' => 9001,
        'voornaam' => 'Old',
        'achternaam' => 'Name',
        'actief' => true,
        'beheerd_in_admin' => false,
    ]);

    $this->artisan('import:sad-members', ['path' => $this->fixturePath])
        ->assertExitCode(0);

    // Sub-resources should have been added by the import
    $relatie->refresh();
    expect($relatie->adressen()->count())->toBeGreaterThan(0);
    expect($relatie->emails()->count())->toBeGreaterThan(0);
});

test('dry-run flag does not persist', function () {
    $this->artisan('import:sad-members', [
        'path' => $this->fixturePath,
        '--dry-run' => true,
    ])->assertExitCode(0);

    expect(Relatie::count())->toBe(0);
});
