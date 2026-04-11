<?php

use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\User;
use App\Services\Google\GoogleContactSyncService;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RelatieTypeSeeder::class);
    Bus::fake();
});

// --- Store type with onderdeel_id ---

test('admin can add type with onderdeel_id', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $dirigentType = RelatieType::where('naam', 'dirigent')->first();
    $onderdeel = Onderdeel::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/types", [
        'relatie_type_id' => $dirigentType->id,
        'van' => '2026-01-01',
        'onderdeel_id' => $onderdeel->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_relatie_type', [
        'relatie_id' => $relatie->id,
        'relatie_type_id' => $dirigentType->id,
        'onderdeel_id' => $onderdeel->id,
    ]);
});

test('admin can add type without onderdeel_id', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $dirigentType = RelatieType::where('naam', 'dirigent')->first();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/types", [
        'relatie_type_id' => $dirigentType->id,
        'van' => '2026-01-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_relatie_type', [
        'relatie_id' => $relatie->id,
        'relatie_type_id' => $dirigentType->id,
        'onderdeel_id' => null,
    ]);
});

test('store type rejects invalid onderdeel_id', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $dirigentType = RelatieType::where('naam', 'dirigent')->first();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/types", [
        'relatie_type_id' => $dirigentType->id,
        'van' => '2026-01-01',
        'onderdeel_id' => 99999,
    ]);

    $response->assertSessionHasErrors('onderdeel_id');
});

// --- Update type with onderdeel_id ---

test('admin can update type with onderdeel_id', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $dirigentType = RelatieType::where('naam', 'dirigent')->first();
    $onderdeel = Onderdeel::factory()->create();

    $relatie->types()->attach($dirigentType->id, ['van' => '2026-01-01']);
    $pivotId = DB::table('soli_relatie_relatie_type')
        ->where('relatie_id', $relatie->id)
        ->where('relatie_type_id', $dirigentType->id)
        ->value('id');

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/types/{$pivotId}", [
        'van' => '2026-01-01',
        'onderdeel_id' => $onderdeel->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_relatie_type', [
        'id' => $pivotId,
        'onderdeel_id' => $onderdeel->id,
    ]);
});

// --- Onderdeel show filters types ---

test('onderdeel show only shows types linked to that onderdeel or unlinked', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $onderdeel1 = Onderdeel::factory()->create(['naam' => 'Harmonie']);
    $onderdeel2 = Onderdeel::factory()->create(['naam' => 'Bigband']);
    $dirigentType = RelatieType::where('naam', 'dirigent')->first();
    $lidType = RelatieType::where('naam', 'lid')->first();

    $relatie = Relatie::factory()->create();
    $relatie->onderdelen()->attach($onderdeel1->id, ['van' => now()->subYear()->toDateString()]);

    // Dirigent linked to onderdeel1 — should be visible on onderdeel1 show
    $relatie->types()->attach($dirigentType->id, [
        'van' => now()->subYear()->toDateString(),
        'onderdeel_id' => $onderdeel1->id,
    ]);

    // Lid with no onderdeel — should also be visible (unlinked types always show)
    $relatie->types()->attach($lidType->id, [
        'van' => now()->subYear()->toDateString(),
        'onderdeel_id' => null,
    ]);

    $response = $this->actingAs($admin)->get("/admin/onderdelen/{$onderdeel1->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/onderdelen/show')
        ->has('onderdeel.relaties', 1)
        ->has('onderdeel.relaties.0.types', 2)
    );
});

test('onderdeel show hides types linked to a different onderdeel', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $onderdeel1 = Onderdeel::factory()->create(['naam' => 'Harmonie']);
    $onderdeel2 = Onderdeel::factory()->create(['naam' => 'Bigband']);
    $dirigentType = RelatieType::where('naam', 'dirigent')->first();

    $relatie = Relatie::factory()->create();
    $relatie->onderdelen()->attach($onderdeel1->id, ['van' => now()->subYear()->toDateString()]);

    // Dirigent linked to onderdeel2 — should NOT be visible on onderdeel1 show
    $relatie->types()->attach($dirigentType->id, [
        'van' => now()->subYear()->toDateString(),
        'onderdeel_id' => $onderdeel2->id,
    ]);

    $response = $this->actingAs($admin)->get("/admin/onderdelen/{$onderdeel1->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/onderdelen/show')
        ->has('onderdeel.relaties', 1)
        ->has('onderdeel.relaties.0.types', 0)
    );
});

// --- Wizard store with onderdeel_id ---

test('wizard store includes onderdeel_id in type pivot', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $dirigentType = RelatieType::where('naam', 'dirigent')->first();
    $onderdeel = Onderdeel::factory()->create();

    $response = $this->actingAs($admin)->post('/admin/relaties', [
        'relatie_nummer' => 8001,
        'voornaam' => 'Test',
        'achternaam' => 'Dirigent',
        'geslacht' => 'M',
        'emails' => [
            ['email' => 'dirigent-test@example.com'],
        ],
        'types' => [
            [
                'type_id' => $dirigentType->id,
                'van' => '2026-01-01',
                'onderdeel_id' => $onderdeel->id,
            ],
        ],
    ]);

    $response->assertRedirect();

    $relatie = Relatie::where('relatie_nummer', 8001)->first();
    $this->assertDatabaseHas('soli_relatie_relatie_type', [
        'relatie_id' => $relatie->id,
        'relatie_type_id' => $dirigentType->id,
        'onderdeel_id' => $onderdeel->id,
    ]);
});

// --- computeDataHash ---

test('computeDataHash changes when type onderdeel_id changes', function () {
    $relatie = Relatie::factory()->create();
    $dirigentType = RelatieType::where('naam', 'dirigent')->first();
    $onderdeel = Onderdeel::factory()->create();

    $relatie->types()->attach($dirigentType->id, [
        'van' => now()->subYear()->toDateString(),
        'onderdeel_id' => null,
    ]);
    $relatie->load(['emails', 'onderdelen', 'types']);

    $service = app(GoogleContactSyncService::class);
    $hash1 = $service->computeDataHash($relatie);

    // Update onderdeel_id on the pivot
    DB::table('soli_relatie_relatie_type')
        ->where('relatie_id', $relatie->id)
        ->where('relatie_type_id', $dirigentType->id)
        ->update(['onderdeel_id' => $onderdeel->id]);

    $relatie->load('types');
    $hash2 = $service->computeDataHash($relatie);

    expect($hash1)->not->toBe($hash2);
});

// --- onderdeel_koppelbaar seeder ---

test('dirigent and contactpersoon have onderdeel_koppelbaar set to true', function () {
    $dirigent = RelatieType::where('naam', 'dirigent')->first();
    $contactpersoon = RelatieType::where('naam', 'contactpersoon')->first();
    $lid = RelatieType::where('naam', 'lid')->first();

    expect($dirigent->onderdeel_koppelbaar)->toBeTrue();
    expect($contactpersoon->onderdeel_koppelbaar)->toBeTrue();
    expect($lid->onderdeel_koppelbaar)->toBeFalse();
});
