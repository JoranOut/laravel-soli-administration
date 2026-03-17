<?php

use App\Models\Relatie;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// --- Store ---

test('admin can add diploma', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/diplomas", [
        'naam' => 'HaFaBra B',
        'instrument' => 'Trompet',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_diplomas', [
        'relatie_id' => $relatie->id,
        'naam' => 'HaFaBra B',
        'instrument' => 'Trompet',
    ]);
});

test('admin can add diploma without instrument', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/diplomas", [
        'naam' => 'EHBO diploma',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_diplomas', [
        'relatie_id' => $relatie->id,
        'naam' => 'EHBO diploma',
        'instrument' => null,
    ]);
});

test('bestuur cannot add diploma', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($bestuur)->post("/admin/relaties/{$relatie->id}/diplomas", [
        'naam' => 'HaFaBra A',
    ]);

    $response->assertForbidden();
});

test('ledenadministratie can add diploma', function () {
    $ledenadmin = User::factory()->create()->assignRole('ledenadministratie');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($ledenadmin)->post("/admin/relaties/{$relatie->id}/diplomas", [
        'naam' => 'HaFaBra A',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_diplomas', [
        'relatie_id' => $relatie->id,
        'naam' => 'HaFaBra A',
    ]);
});

test('member cannot add diploma', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($member)->post("/admin/relaties/{$relatie->id}/diplomas", [
        'naam' => 'HaFaBra B',
    ]);

    $response->assertForbidden();
});

test('guest cannot add diploma', function () {
    $relatie = Relatie::factory()->create();

    $response = $this->post("/admin/relaties/{$relatie->id}/diplomas", [
        'naam' => 'HaFaBra B',
    ]);

    $response->assertRedirect('/login');
});

// --- Update ---

test('admin can update diploma', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $diploma = $relatie->diplomas()->create([
        'naam' => 'HaFaBra A',
        'instrument' => 'Trompet',
    ]);

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/diplomas/{$diploma->id}", [
        'naam' => 'HaFaBra B',
        'instrument' => 'Klarinet',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_diplomas', [
        'id' => $diploma->id,
        'naam' => 'HaFaBra B',
        'instrument' => 'Klarinet',
    ]);
});

test('member cannot update diploma', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create();
    $diploma = $relatie->diplomas()->create([
        'naam' => 'HaFaBra A',
    ]);

    $response = $this->actingAs($member)->put("/admin/relaties/{$relatie->id}/diplomas/{$diploma->id}", [
        'naam' => 'HaFaBra B',
    ]);

    $response->assertForbidden();
});

test('guest cannot update diploma', function () {
    $relatie = Relatie::factory()->create();
    $diploma = $relatie->diplomas()->create([
        'naam' => 'HaFaBra A',
    ]);

    $response = $this->put("/admin/relaties/{$relatie->id}/diplomas/{$diploma->id}", [
        'naam' => 'HaFaBra B',
    ]);

    $response->assertRedirect('/login');
});

// --- Delete ---

test('admin can delete diploma', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $diploma = $relatie->diplomas()->create([
        'naam' => 'Te verwijderen',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/diplomas/{$diploma->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_diplomas', ['id' => $diploma->id]);
});

test('member cannot delete diploma', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create();
    $diploma = $relatie->diplomas()->create([
        'naam' => 'Te verwijderen',
    ]);

    $response = $this->actingAs($member)->delete("/admin/relaties/{$relatie->id}/diplomas/{$diploma->id}");

    $response->assertForbidden();
});

test('guest cannot delete diploma', function () {
    $relatie = Relatie::factory()->create();
    $diploma = $relatie->diplomas()->create([
        'naam' => 'Te verwijderen',
    ]);

    $response = $this->delete("/admin/relaties/{$relatie->id}/diplomas/{$diploma->id}");

    $response->assertRedirect('/login');
});

// --- Validation ---

test('store diploma requires naam', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/diplomas", []);

    $response->assertSessionHasErrors(['naam']);
});
