<?php

use App\Models\Insigne;
use App\Models\Relatie;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// --- Store ---

test('admin can add insigne', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/insignes", [
        'naam' => 'Ere-insigne',
        'datum' => '2026-03-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_insignes', [
        'relatie_id' => $relatie->id,
        'naam' => 'Ere-insigne',
        'datum' => '2026-03-01',
    ]);
});

test('bestuur cannot add insigne', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($bestuur)->post("/admin/relaties/{$relatie->id}/insignes", [
        'naam' => 'Ere-insigne',
        'datum' => '2026-03-01',
    ]);

    $response->assertForbidden();
});

test('ledenadministratie can add insigne', function () {
    $ledenadmin = User::factory()->create()->assignRole('ledenadministratie');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($ledenadmin)->post("/admin/relaties/{$relatie->id}/insignes", [
        'naam' => 'Ere-insigne',
        'datum' => '2026-03-01',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_insignes', [
        'relatie_id' => $relatie->id,
        'naam' => 'Ere-insigne',
    ]);
});

test('member cannot add insigne', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($member)->post("/admin/relaties/{$relatie->id}/insignes", [
        'naam' => 'Ere-insigne',
        'datum' => '2026-03-01',
    ]);

    $response->assertForbidden();
});

test('guest cannot add insigne', function () {
    $relatie = Relatie::factory()->create();

    $response = $this->post("/admin/relaties/{$relatie->id}/insignes", [
        'naam' => 'Ere-insigne',
        'datum' => '2026-03-01',
    ]);

    $response->assertRedirect('/login');
});

// --- Update ---

test('admin can update insigne', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $insigne = $relatie->insignes()->create([
        'naam' => 'Origineel',
        'datum' => '2025-01-01',
    ]);

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/insignes/{$insigne->id}", [
        'naam' => 'Bijgewerkt',
        'datum' => '2026-06-15',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_insignes', [
        'id' => $insigne->id,
        'naam' => 'Bijgewerkt',
        'datum' => '2026-06-15',
    ]);
});

test('member cannot update insigne', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create();
    $insigne = $relatie->insignes()->create([
        'naam' => 'Origineel',
        'datum' => '2025-01-01',
    ]);

    $response = $this->actingAs($member)->put("/admin/relaties/{$relatie->id}/insignes/{$insigne->id}", [
        'naam' => 'Bijgewerkt',
        'datum' => '2026-06-15',
    ]);

    $response->assertForbidden();
});

test('guest cannot update insigne', function () {
    $relatie = Relatie::factory()->create();
    $insigne = $relatie->insignes()->create([
        'naam' => 'Origineel',
        'datum' => '2025-01-01',
    ]);

    $response = $this->put("/admin/relaties/{$relatie->id}/insignes/{$insigne->id}", [
        'naam' => 'Bijgewerkt',
        'datum' => '2026-06-15',
    ]);

    $response->assertRedirect('/login');
});

// --- Delete ---

test('admin can delete insigne', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $insigne = $relatie->insignes()->create([
        'naam' => 'Te verwijderen',
        'datum' => '2025-01-01',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/insignes/{$insigne->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_insignes', ['id' => $insigne->id]);
});

test('member cannot delete insigne', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create();
    $insigne = $relatie->insignes()->create([
        'naam' => 'Te verwijderen',
        'datum' => '2025-01-01',
    ]);

    $response = $this->actingAs($member)->delete("/admin/relaties/{$relatie->id}/insignes/{$insigne->id}");

    $response->assertForbidden();
});

test('guest cannot delete insigne', function () {
    $relatie = Relatie::factory()->create();
    $insigne = $relatie->insignes()->create([
        'naam' => 'Te verwijderen',
        'datum' => '2025-01-01',
    ]);

    $response = $this->delete("/admin/relaties/{$relatie->id}/insignes/{$insigne->id}");

    $response->assertRedirect('/login');
});

// --- Validation ---

test('store insigne requires naam and datum', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/insignes", []);

    $response->assertSessionHasErrors(['naam', 'datum']);
});
