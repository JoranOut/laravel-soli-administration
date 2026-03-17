<?php

use App\Models\Instrument;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin can add reparatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $instrument = Instrument::factory()->create(['status' => 'beschikbaar']);

    $response = $this->actingAs($admin)->post("/admin/instrumenten/{$instrument->id}/reparaties", [
        'beschrijving' => 'Ventiel vervanging',
        'reparateur' => 'Muziekwinkel BV',
        'kosten' => 150.00,
        'datum_in' => '2026-03-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_reparaties', [
        'instrument_id' => $instrument->id,
        'beschrijving' => 'Ventiel vervanging',
        'reparateur' => 'Muziekwinkel BV',
    ]);
    $this->assertDatabaseHas('soli_instrumenten', [
        'id' => $instrument->id,
        'status' => 'in_reparatie',
    ]);
});

test('reparatie with datum_uit does not change status', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $instrument = Instrument::factory()->create(['status' => 'beschikbaar']);

    $response = $this->actingAs($admin)->post("/admin/instrumenten/{$instrument->id}/reparaties", [
        'beschrijving' => 'Kleine reparatie',
        'datum_in' => '2026-02-01',
        'datum_uit' => '2026-02-15',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrumenten', [
        'id' => $instrument->id,
        'status' => 'beschikbaar',
    ]);
});

test('admin can update reparatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $instrument = Instrument::factory()->create();
    $reparatie = $instrument->reparaties()->create([
        'beschrijving' => 'Origineel',
        'datum_in' => '2026-03-01',
    ]);

    $response = $this->actingAs($admin)->put("/admin/instrumenten/{$instrument->id}/reparaties/{$reparatie->id}", [
        'beschrijving' => 'Bijgewerkt',
        'reparateur' => 'Andere winkel',
        'kosten' => 200.00,
        'datum_in' => '2026-03-01',
        'datum_uit' => '2026-03-15',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrument_reparaties', [
        'id' => $reparatie->id,
        'beschrijving' => 'Bijgewerkt',
        'reparateur' => 'Andere winkel',
    ]);
});

test('admin can delete reparatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $instrument = Instrument::factory()->create();
    $reparatie = $instrument->reparaties()->create([
        'beschrijving' => 'Te verwijderen',
        'datum_in' => '2026-03-01',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/instrumenten/{$instrument->id}/reparaties/{$reparatie->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_instrument_reparaties', ['id' => $reparatie->id]);
});

test('member cannot add reparatie', function () {
    $member = User::factory()->create()->assignRole('member');
    $instrument = Instrument::factory()->create();

    $response = $this->actingAs($member)->post("/admin/instrumenten/{$instrument->id}/reparaties", [
        'beschrijving' => 'Test',
        'datum_in' => '2026-03-01',
    ]);

    $response->assertForbidden();
});
