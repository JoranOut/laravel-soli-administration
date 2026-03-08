<?php

use App\Models\Onderdeel;
use App\Models\Opleiding;
use App\Models\Relatie;
use App\Models\RelatieSinds;
use App\Models\RelatieType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RelatieTypeSeeder::class);
});

// --- Relatie Types ---

test('admin can attach type to relatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $type = RelatieType::where('naam', 'lid')->first();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/types", [
        'relatie_type_id' => $type->id,
        'van' => '2026-01-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_relatie_type', [
        'relatie_id' => $relatie->id,
        'relatie_type_id' => $type->id,
        'van' => '2026-01-01',
    ]);
});

test('admin can update type on relatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $type = RelatieType::where('naam', 'lid')->first();

    $relatie->types()->attach($type->id, ['van' => '2025-01-01']);
    $pivotId = DB::table('soli_relatie_relatie_type')
        ->where('relatie_id', $relatie->id)
        ->where('relatie_type_id', $type->id)
        ->value('id');

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/types/{$pivotId}", [
        'van' => '2024-06-01',
        'tot' => '2026-12-31',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_relatie_type', [
        'id' => $pivotId,
        'van' => '2024-06-01',
        'tot' => '2026-12-31',
    ]);
});

test('admin can detach type from relatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $type = RelatieType::where('naam', 'donateur')->first();

    $relatie->types()->attach($type->id, ['van' => '2025-01-01']);
    $pivotId = DB::table('soli_relatie_relatie_type')
        ->where('relatie_id', $relatie->id)
        ->where('relatie_type_id', $type->id)
        ->value('id');

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/types/{$pivotId}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_relatie_relatie_type', ['id' => $pivotId]);
});

test('admin can attach type with functie and email', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $type = RelatieType::where('naam', 'bestuur')->first();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/types", [
        'relatie_type_id' => $type->id,
        'van' => '2026-01-01',
        'functie' => 'Voorzitter',
        'email' => 'voorzitter@soli.nl',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_relatie_type', [
        'relatie_id' => $relatie->id,
        'relatie_type_id' => $type->id,
        'van' => '2026-01-01',
        'functie' => 'Voorzitter',
        'email' => 'voorzitter@soli.nl',
    ]);
});

test('admin can update type with functie and email', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $type = RelatieType::where('naam', 'bestuur')->first();

    $relatie->types()->attach($type->id, ['van' => '2025-01-01', 'functie' => 'Secretaris']);
    $pivotId = DB::table('soli_relatie_relatie_type')
        ->where('relatie_id', $relatie->id)
        ->where('relatie_type_id', $type->id)
        ->value('id');

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/types/{$pivotId}", [
        'van' => '2025-01-01',
        'functie' => 'Voorzitter',
        'email' => 'voorzitter@soli.nl',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_relatie_type', [
        'id' => $pivotId,
        'functie' => 'Voorzitter',
        'email' => 'voorzitter@soli.nl',
    ]);
});

// --- Lidmaatschap ---

test('admin can add lidmaatschap', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/lidmaatschap", [
        'lid_sinds' => '2020-01-01',
        'lid_tot' => null,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_sinds', [
        'relatie_id' => $relatie->id,
        'lid_sinds' => '2020-01-01',
    ]);
});

test('admin can update lidmaatschap', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $lidmaatschap = $relatie->relatieSinds()->create([
        'lid_sinds' => '2020-01-01',
    ]);

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/lidmaatschap/{$lidmaatschap->id}", [
        'lid_sinds' => '2019-06-01',
        'lid_tot' => '2026-12-31',
        'reden_vertrek' => 'Verhuisd',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_sinds', [
        'id' => $lidmaatschap->id,
        'lid_sinds' => '2019-06-01',
        'reden_vertrek' => 'Verhuisd',
    ]);
});

test('admin can delete lidmaatschap', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $lidmaatschap = $relatie->relatieSinds()->create([
        'lid_sinds' => '2020-01-01',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/lidmaatschap/{$lidmaatschap->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_relatie_sinds', ['id' => $lidmaatschap->id]);
});

// --- Onderdelen (pivot) ---

test('admin can attach onderdeel to relatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $onderdeel = Onderdeel::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/onderdelen", [
        'onderdeel_id' => $onderdeel->id,
        'functie' => 'muzikant',
        'van' => '2026-01-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_onderdeel', [
        'relatie_id' => $relatie->id,
        'onderdeel_id' => $onderdeel->id,
        'functie' => 'muzikant',
    ]);
});

test('admin can update onderdeel on relatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $onderdeel = Onderdeel::factory()->create();

    $relatie->onderdelen()->attach($onderdeel->id, ['van' => '2025-01-01']);
    $pivotId = DB::table('soli_relatie_onderdeel')
        ->where('relatie_id', $relatie->id)
        ->where('onderdeel_id', $onderdeel->id)
        ->value('id');

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/onderdelen/{$pivotId}", [
        'functie' => 'dirigent',
        'van' => '2025-01-01',
        'tot' => '2026-12-31',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_onderdeel', [
        'id' => $pivotId,
        'functie' => 'dirigent',
        'tot' => '2026-12-31',
    ]);
});

test('admin can detach onderdeel from relatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $onderdeel = Onderdeel::factory()->create();

    $relatie->onderdelen()->attach($onderdeel->id, ['van' => '2025-01-01']);
    $pivotId = DB::table('soli_relatie_onderdeel')
        ->where('relatie_id', $relatie->id)
        ->where('onderdeel_id', $onderdeel->id)
        ->value('id');

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/onderdelen/{$pivotId}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_relatie_onderdeel', ['id' => $pivotId]);
});

// --- Opleidingen ---

test('admin can add opleiding', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/opleidingen", [
        'naam' => 'HaFaBra A',
        'instituut' => 'Muziekschool Kennemerland',
        'instrument' => 'Trompet',
        'datum_start' => '2024-09-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_opleidingen', [
        'relatie_id' => $relatie->id,
        'naam' => 'HaFaBra A',
        'instrument' => 'Trompet',
    ]);
});

test('admin can update opleiding', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $opleiding = $relatie->opleidingen()->create([
        'naam' => 'Origineel',
        'datum_start' => '2024-09-01',
    ]);

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/opleidingen/{$opleiding->id}", [
        'naam' => 'HaFaBra B',
        'instituut' => 'Conservatorium',
        'diploma' => 'B-diploma',
        'datum_start' => '2024-09-01',
        'datum_einde' => '2026-06-30',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_opleidingen', [
        'id' => $opleiding->id,
        'naam' => 'HaFaBra B',
        'diploma' => 'B-diploma',
    ]);
});

test('admin can delete opleiding', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $opleiding = $relatie->opleidingen()->create([
        'naam' => 'Te verwijderen',
        'datum_start' => '2024-01-01',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/opleidingen/{$opleiding->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_opleidingen', ['id' => $opleiding->id]);
});

// --- Authorization ---

test('member cannot mutate relatie sub-resources', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create();
    $type = RelatieType::where('naam', 'lid')->first();

    $response = $this->actingAs($member)->post("/admin/relaties/{$relatie->id}/types", [
        'relatie_type_id' => $type->id,
        'van' => '2026-01-01',
    ]);

    $response->assertForbidden();
});

test('bestuur cannot mutate relatie sub-resources', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($bestuur)->post("/admin/relaties/{$relatie->id}/lidmaatschap", [
        'lid_sinds' => '2026-01-01',
    ]);

    $response->assertForbidden();
});

test('ledenadministratie can mutate relatie sub-resources', function () {
    $ledenadmin = User::factory()->create()->assignRole('ledenadministratie');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($ledenadmin)->post("/admin/relaties/{$relatie->id}/lidmaatschap", [
        'lid_sinds' => '2026-01-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relatie_sinds', [
        'relatie_id' => $relatie->id,
        'lid_sinds' => '2026-01-01',
    ]);
});
