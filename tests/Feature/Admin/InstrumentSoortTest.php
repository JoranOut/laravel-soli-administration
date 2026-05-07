<?php

use App\Models\InstrumentFamilie;
use App\Models\InstrumentSoort;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

function createFamilie(string $naam = 'Trompet'): InstrumentFamilie
{
    return InstrumentFamilie::create(['naam' => $naam]);
}

function createSoort(string $naam = 'Trompet', ?InstrumentFamilie $familie = null): InstrumentSoort
{
    $familie ??= createFamilie();

    return InstrumentSoort::create(['naam' => $naam, 'instrument_familie_id' => $familie->id]);
}

test('admin can view instrumentsoorten index', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie();
    InstrumentSoort::create(['naam' => 'Trompet', 'instrument_familie_id' => $familie->id]);
    InstrumentSoort::create(['naam' => 'Klarinet', 'instrument_familie_id' => createFamilie('Klarinet')->id]);

    $response = $this->actingAs($admin)->get('/admin/instrumentsoorten');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/instrumentsoorten/index')
        ->has('instrumentSoorten', 2)
        ->has('families')
    );
});

test('bestuur can view instrumentsoorten', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    createSoort();

    $response = $this->actingAs($bestuur)->get('/admin/instrumentsoorten');

    $response->assertOk();
});

test('member cannot access instrumentsoorten', function () {
    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get('/admin/instrumentsoorten');

    $response->assertForbidden();
});

test('guest is redirected to login', function () {
    $response = $this->get('/admin/instrumentsoorten');

    $response->assertRedirect('/login');
});

// Seeder data integrity tests

test('seeder creates all expected families', function () {
    $this->seed(\Database\Seeders\InstrumentSoortSeeder::class);

    $expectedFamilies = [
        'Bas', 'Directiepartijen', 'Diverse', 'Dwarsfluit',
        'Fagot', 'Gitaar', 'Hobo', 'Hoorn', 'Klarinet',
        'Klein koper', 'Saxofoon', 'Slagwerk', 'Toetsen',
        'Trombone', 'Tuba', 'Zang',
    ];

    foreach ($expectedFamilies as $naam) {
        $this->assertDatabaseHas('soli_instrument_families', ['naam' => $naam]);
    }

    expect(InstrumentFamilie::count())->toBe(count($expectedFamilies));
});

test('seeder creates percussion instruments under slagwerk', function () {
    $this->seed(\Database\Seeders\InstrumentSoortSeeder::class);

    $slagwerk = InstrumentFamilie::where('naam', 'Slagwerk')->first();

    $expectedSoorten = [
        'Slagwerk', 'Melodisch slagwerk', 'Paradetrom', 'Kleine trom',
        'Trom', 'Trio tom', 'Bekken', 'Pauken', 'Marimba',
        'Vibrafoon', 'Xylofoon', 'Percussion', 'Buisklokken',
        'Drumstel', 'Klokkenspel',
    ];

    foreach ($expectedSoorten as $naam) {
        $this->assertDatabaseHas('soli_instrument_soorten', [
            'naam' => $naam,
            'instrument_familie_id' => $slagwerk->id,
        ]);
    }
});

test('seeder places tamboer-maître in directiepartijen family', function () {
    $this->seed(\Database\Seeders\InstrumentSoortSeeder::class);

    $familie = InstrumentFamilie::where('naam', 'Directiepartijen')->first();
    $soort = InstrumentSoort::where('naam', 'Tamboer-maître')->first();

    expect($familie)->not->toBeNull();
    expect($soort->instrument_familie_id)->toBe($familie->id);
});
