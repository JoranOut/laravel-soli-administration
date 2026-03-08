<?php

use App\Models\Adres;
use App\Models\Email;
use App\Models\GiroGegeven;
use App\Models\Relatie;
use App\Models\Telefoon;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// --- Adressen ---

test('admin can add adres', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/adressen", [
        'straat' => 'Dorpsstraat',
        'huisnummer' => '10',
        'postcode' => '1985AA',
        'plaats' => 'Driehuis',
        'land' => 'Nederland',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_adressen', [
        'relatie_id' => $relatie->id,
        'straat' => 'Dorpsstraat',
        'plaats' => 'Driehuis',
    ]);
});

test('admin can update adres', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $adres = $relatie->adressen()->create([
        'straat' => 'Oude Straat',
        'huisnummer' => '1',
        'postcode' => '1000AA',
        'plaats' => 'Amsterdam',
        'land' => 'Nederland',
    ]);

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/adressen/{$adres->id}", [
        'straat' => 'Nieuwe Straat',
        'huisnummer' => '2',
        'postcode' => '1000BB',
        'plaats' => 'Rotterdam',
        'land' => 'Nederland',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_adressen', [
        'id' => $adres->id,
        'straat' => 'Nieuwe Straat',
        'plaats' => 'Rotterdam',
    ]);
});

test('admin can delete adres', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $adres = $relatie->adressen()->create([
        'straat' => 'Te Verwijderen',
        'huisnummer' => '1',
        'postcode' => '1000AA',
        'plaats' => 'Test',
        'land' => 'Nederland',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/adressen/{$adres->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_adressen', ['id' => $adres->id]);
});

// --- Emails ---

test('admin can add email', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/emails", [
        'email' => 'test@example.com',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_emails', [
        'relatie_id' => $relatie->id,
        'email' => 'test@example.com',
    ]);
});

test('admin can update email', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $email = $relatie->emails()->create([
        'email' => 'oud@example.com',
    ]);

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/emails/{$email->id}", [
        'email' => 'nieuw@example.com',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_emails', [
        'id' => $email->id,
        'email' => 'nieuw@example.com',
    ]);
});

test('admin can delete email', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $email = $relatie->emails()->create([
        'email' => 'delete@example.com',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/emails/{$email->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_emails', ['id' => $email->id]);
});

// --- Telefoons ---

test('admin can add telefoon', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/telefoons", [
        'nummer' => '0612345678',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_telefoons', [
        'relatie_id' => $relatie->id,
        'nummer' => '0612345678',
    ]);
});

test('admin can update telefoon', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $telefoon = $relatie->telefoons()->create([
        'nummer' => '0201234567',
    ]);

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/telefoons/{$telefoon->id}", [
        'nummer' => '0698765432',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_telefoons', [
        'id' => $telefoon->id,
        'nummer' => '0698765432',
    ]);
});

test('admin can delete telefoon', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $telefoon = $relatie->telefoons()->create([
        'nummer' => '0201111111',
    ]);

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/telefoons/{$telefoon->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_telefoons', ['id' => $telefoon->id]);
});

// --- Giro gegevens ---

test('admin can add giro gegeven', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/giro-gegevens", [
        'iban' => 'NL91ABNA0417164300',
        'bic' => 'ABNANL2A',
        'tenaamstelling' => 'J. Jansen',
        'machtiging' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_giro_gegevens', [
        'relatie_id' => $relatie->id,
        'iban' => 'NL91ABNA0417164300',
        'tenaamstelling' => 'J. Jansen',
    ]);
});

test('admin can update giro gegeven', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $giro = $relatie->giroGegevens()->create([
        'iban' => 'NL91ABNA0417164300',
        'tenaamstelling' => 'Oud',
        'machtiging' => false,
    ]);

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/giro-gegevens/{$giro->id}", [
        'iban' => 'NL91RABO0123456789',
        'tenaamstelling' => 'Nieuw',
        'machtiging' => true,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_giro_gegevens', [
        'id' => $giro->id,
        'iban' => 'NL91RABO0123456789',
        'tenaamstelling' => 'Nieuw',
    ]);
});

test('admin can delete giro gegeven', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $giro = $relatie->giroGegevens()->create([
        'iban' => 'NL91ABNA0417164300',
        'tenaamstelling' => 'Test',
        'machtiging' => false,
    ]);

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/giro-gegevens/{$giro->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_giro_gegevens', ['id' => $giro->id]);
});

// --- Authorization ---

test('member cannot add adres', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($member)->post("/admin/relaties/{$relatie->id}/adressen", [
        'straat' => 'Verboden',
        'huisnummer' => '1',
        'postcode' => '1000AA',
        'plaats' => 'Test',
        'land' => 'Nederland',
    ]);

    $response->assertForbidden();
});

test('bestuur cannot add adres', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($bestuur)->post("/admin/relaties/{$relatie->id}/adressen", [
        'straat' => 'Bestuurstraat',
        'huisnummer' => '5',
        'postcode' => '1985BB',
        'plaats' => 'Driehuis',
        'land' => 'Nederland',
    ]);

    $response->assertForbidden();
});

test('ledenadministratie can add adres', function () {
    $ledenadmin = User::factory()->create()->assignRole('ledenadministratie');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($ledenadmin)->post("/admin/relaties/{$relatie->id}/adressen", [
        'straat' => 'Adminstraat',
        'huisnummer' => '5',
        'postcode' => '1985BB',
        'plaats' => 'Driehuis',
        'land' => 'Nederland',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_adressen', ['straat' => 'Adminstraat']);
});
