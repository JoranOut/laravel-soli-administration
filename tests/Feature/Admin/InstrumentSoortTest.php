<?php

use App\Models\InstrumentFamilie;
use App\Models\InstrumentSoort;
use App\Models\Relatie;
use App\Models\RelatieInstrument;
use App\Models\Onderdeel;
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

test('admin can create instrument soort', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie();

    $response = $this->actingAs($admin)->post('/admin/instrumentsoorten', [
        'naam' => 'Trompet',
        'instrument_familie_id' => $familie->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_soorten', [
        'naam' => 'Trompet',
        'instrument_familie_id' => $familie->id,
    ]);
});

test('admin can update instrument soort', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie();
    $soort = createSoort('Trompet', $familie);

    $response = $this->actingAs($admin)->put("/admin/instrumentsoorten/{$soort->id}", [
        'naam' => 'Cornet',
        'instrument_familie_id' => $familie->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_soorten', [
        'id' => $soort->id,
        'naam' => 'Cornet',
    ]);
});

test('admin can delete instrument soort without linked members', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $soort = createSoort();

    $response = $this->actingAs($admin)->delete("/admin/instrumentsoorten/{$soort->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_instrument_soorten', ['id' => $soort->id]);
});

test('cannot delete instrument soort with linked members', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $soort = createSoort();
    $relatie = Relatie::factory()->create();
    $onderdeel = Onderdeel::factory()->create();

    RelatieInstrument::create([
        'relatie_id' => $relatie->id,
        'onderdeel_id' => $onderdeel->id,
        'instrument_soort_id' => $soort->id,
    ]);

    $response = $this->actingAs($admin)->delete("/admin/instrumentsoorten/{$soort->id}");

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('soli_instrument_soorten', ['id' => $soort->id]);
});

test('naam must be unique on create', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie();
    InstrumentSoort::create(['naam' => 'Trompet', 'instrument_familie_id' => $familie->id]);

    $response = $this->actingAs($admin)->post('/admin/instrumentsoorten', [
        'naam' => 'Trompet',
        'instrument_familie_id' => $familie->id,
    ]);

    $response->assertSessionHasErrors('naam');
});

test('naam must be unique on update except self', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie();
    InstrumentSoort::create(['naam' => 'Trompet', 'instrument_familie_id' => $familie->id]);
    $soort = InstrumentSoort::create(['naam' => 'Klarinet', 'instrument_familie_id' => $familie->id]);

    $response = $this->actingAs($admin)->put("/admin/instrumentsoorten/{$soort->id}", [
        'naam' => 'Trompet',
        'instrument_familie_id' => $familie->id,
    ]);

    $response->assertSessionHasErrors('naam');
});

test('updating instrument soort can keep its own naam', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie();
    $newFamilie = createFamilie('Koper');
    $soort = createSoort('Trompet', $familie);

    $response = $this->actingAs($admin)->put("/admin/instrumentsoorten/{$soort->id}", [
        'naam' => 'Trompet',
        'instrument_familie_id' => $newFamilie->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors('naam');
    $this->assertDatabaseHas('soli_instrument_soorten', [
        'id' => $soort->id,
        'instrument_familie_id' => $newFamilie->id,
    ]);
});

test('bestuur can view instrumentsoorten', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    createSoort();

    $response = $this->actingAs($bestuur)->get('/admin/instrumentsoorten');

    $response->assertOk();
});

test('bestuur cannot create instrument soort', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    $familie = createFamilie();

    $response = $this->actingAs($bestuur)->post('/admin/instrumentsoorten', [
        'naam' => 'Trompet',
        'instrument_familie_id' => $familie->id,
    ]);

    $response->assertForbidden();
});

test('bestuur cannot update instrument soort', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    $soort = createSoort();

    $response = $this->actingAs($bestuur)->put("/admin/instrumentsoorten/{$soort->id}", [
        'naam' => 'Cornet',
        'instrument_familie_id' => $soort->instrument_familie_id,
    ]);

    $response->assertForbidden();
});

test('bestuur cannot delete instrument soort', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    $soort = createSoort();

    $response = $this->actingAs($bestuur)->delete("/admin/instrumentsoorten/{$soort->id}");

    $response->assertForbidden();
});

test('member cannot access instrumentsoorten', function () {
    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get('/admin/instrumentsoorten');

    $response->assertForbidden();
});

test('ledenadministratie can manage instrumentsoorten', function () {
    $ledenadmin = User::factory()->create()->assignRole('ledenadministratie');
    $familie = createFamilie('Saxofoon');

    $response = $this->actingAs($ledenadmin)->post('/admin/instrumentsoorten', [
        'naam' => 'Saxofoon',
        'instrument_familie_id' => $familie->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_soorten', ['naam' => 'Saxofoon']);
});

test('guest is redirected to login', function () {
    $response = $this->get('/admin/instrumentsoorten');

    $response->assertRedirect('/login');
});

// Family CRUD tests

test('admin can create family', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/instrumentsoorten/families', [
        'naam' => 'Koper',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_families', ['naam' => 'Koper']);
});

test('admin can update family', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie('Koper');

    $response = $this->actingAs($admin)->put("/admin/instrumentsoorten/families/{$familie->id}", [
        'naam' => 'Houtblazers',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_families', ['id' => $familie->id, 'naam' => 'Houtblazers']);
});

test('admin can delete family without linked soorten', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie('Koper');

    $response = $this->actingAs($admin)->delete("/admin/instrumentsoorten/families/{$familie->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_instrument_families', ['id' => $familie->id]);
});

test('cannot delete family with linked soorten', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie();
    InstrumentSoort::create(['naam' => 'Trompet', 'instrument_familie_id' => $familie->id]);

    $response = $this->actingAs($admin)->delete("/admin/instrumentsoorten/families/{$familie->id}");

    $response->assertRedirect();
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('soli_instrument_families', ['id' => $familie->id]);
});

test('family naam must be unique', function () {
    $admin = User::factory()->create()->assignRole('admin');
    createFamilie('Koper');

    $response = $this->actingAs($admin)->post('/admin/instrumentsoorten/families', [
        'naam' => 'Koper',
    ]);

    $response->assertSessionHasErrors('naam');
});

test('bestuur cannot create family', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');

    $response = $this->actingAs($bestuur)->post('/admin/instrumentsoorten/families', [
        'naam' => 'Koper',
    ]);

    $response->assertForbidden();
});

test('bestuur cannot update family', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    $familie = createFamilie();

    $response = $this->actingAs($bestuur)->put("/admin/instrumentsoorten/families/{$familie->id}", [
        'naam' => 'Houtblazers',
    ]);

    $response->assertForbidden();
});

test('bestuur cannot delete family', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    $familie = createFamilie();

    $response = $this->actingAs($bestuur)->delete("/admin/instrumentsoorten/families/{$familie->id}");

    $response->assertForbidden();
});

test('family naam must be unique on update except self', function () {
    $admin = User::factory()->create()->assignRole('admin');
    createFamilie('Koper');
    $familie = createFamilie('Houtblazers');

    $response = $this->actingAs($admin)->put("/admin/instrumentsoorten/families/{$familie->id}", [
        'naam' => 'Koper',
    ]);

    $response->assertSessionHasErrors('naam');
});

test('updating family can keep its own naam', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie('Koper');

    $response = $this->actingAs($admin)->put("/admin/instrumentsoorten/families/{$familie->id}", [
        'naam' => 'Koper',
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors('naam');
});

test('family naam is required on create', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/instrumentsoorten/families', [
        'naam' => '',
    ]);

    $response->assertSessionHasErrors('naam');
});

test('family naam is required on update', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $familie = createFamilie('Koper');

    $response = $this->actingAs($admin)->put("/admin/instrumentsoorten/families/{$familie->id}", [
        'naam' => '',
    ]);

    $response->assertSessionHasErrors('naam');
});

test('ledenadministratie can manage families', function () {
    $ledenadmin = User::factory()->create()->assignRole('ledenadministratie');

    $response = $this->actingAs($ledenadmin)->post('/admin/instrumentsoorten/families', [
        'naam' => 'Koper',
    ]);

    $response->assertRedirect();
    $familie = InstrumentFamilie::where('naam', 'Koper')->first();

    $response = $this->actingAs($ledenadmin)->put("/admin/instrumentsoorten/families/{$familie->id}", [
        'naam' => 'Houtblazers',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_families', ['id' => $familie->id, 'naam' => 'Houtblazers']);

    $response = $this->actingAs($ledenadmin)->delete("/admin/instrumentsoorten/families/{$familie->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_instrument_families', ['id' => $familie->id]);
});

test('member cannot access family routes', function () {
    $member = User::factory()->create()->assignRole('member');
    $familie = createFamilie();

    $this->actingAs($member)->post('/admin/instrumentsoorten/families', ['naam' => 'Koper'])->assertForbidden();
    $this->actingAs($member)->put("/admin/instrumentsoorten/families/{$familie->id}", ['naam' => 'Koper'])->assertForbidden();
    $this->actingAs($member)->delete("/admin/instrumentsoorten/families/{$familie->id}")->assertForbidden();
});

test('guest is redirected to login for family routes', function () {
    $familie = createFamilie();

    $this->post('/admin/instrumentsoorten/families', ['naam' => 'Koper'])->assertRedirect('/login');
    $this->put("/admin/instrumentsoorten/families/{$familie->id}", ['naam' => 'Koper'])->assertRedirect('/login');
    $this->delete("/admin/instrumentsoorten/families/{$familie->id}")->assertRedirect('/login');
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
