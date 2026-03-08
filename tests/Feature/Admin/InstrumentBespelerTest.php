<?php

use App\Models\Instrument;
use App\Models\InstrumentBespeler;
use App\Models\Relatie;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin can assign bespeler to instrument', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $instrument = Instrument::factory()->create(['status' => 'beschikbaar']);
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/instrumenten/{$instrument->id}/bespelers", [
        'relatie_id' => $relatie->id,
        'van' => '2026-01-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_bespelers', [
        'instrument_id' => $instrument->id,
        'relatie_id' => $relatie->id,
        'van' => '2026-01-01',
    ]);
    $this->assertDatabaseHas('soli_instrumenten', [
        'id' => $instrument->id,
        'status' => 'in_gebruik',
    ]);
});

test('assigning new bespeler closes previous bespeler', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $instrument = Instrument::factory()->create(['status' => 'in_gebruik']);
    $previousRelatie = Relatie::factory()->create();
    $previousBespeler = InstrumentBespeler::create([
        'instrument_id' => $instrument->id,
        'relatie_id' => $previousRelatie->id,
        'van' => '2025-01-01',
        'tot' => null,
    ]);

    $newRelatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/instrumenten/{$instrument->id}/bespelers", [
        'relatie_id' => $newRelatie->id,
        'van' => '2026-03-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_bespelers', [
        'id' => $previousBespeler->id,
        'tot' => '2026-03-01',
    ]);
    $this->assertDatabaseHas('soli_instrument_bespelers', [
        'instrument_id' => $instrument->id,
        'relatie_id' => $newRelatie->id,
        'van' => '2026-03-01',
        'tot' => null,
    ]);
});

test('admin can remove bespeler from instrument', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $instrument = Instrument::factory()->create(['status' => 'in_gebruik']);
    $relatie = Relatie::factory()->create();
    $bespeler = InstrumentBespeler::create([
        'instrument_id' => $instrument->id,
        'relatie_id' => $relatie->id,
        'van' => '2026-01-01',
        'tot' => null,
    ]);

    $response = $this->actingAs($admin)->delete("/admin/instrumenten/{$instrument->id}/bespelers/{$bespeler->id}");

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_bespelers', [
        'id' => $bespeler->id,
        'tot' => now()->toDateString(),
    ]);
    $this->assertDatabaseHas('soli_instrumenten', [
        'id' => $instrument->id,
        'status' => 'beschikbaar',
    ]);
});

test('member cannot assign bespeler', function () {
    $member = User::factory()->create()->assignRole('member');
    $instrument = Instrument::factory()->create();
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($member)->post("/admin/instrumenten/{$instrument->id}/bespelers", [
        'relatie_id' => $relatie->id,
        'van' => '2026-01-01',
    ]);

    $response->assertForbidden();
});
