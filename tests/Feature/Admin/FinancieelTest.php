<?php

use App\Models\Contributie;
use App\Models\Relatie;
use App\Models\SoortContributie;
use App\Models\Tariefgroep;
use App\Models\TeBetakenContributie;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SoortContributieSeeder;
use Database\Seeders\TariefgroepSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(SoortContributieSeeder::class);
    $this->seed(TariefgroepSeeder::class);
});

// --- Tariefgroepen ---

test('admin can view tariefgroepen', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/financieel/tariefgroepen');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/financieel/tariefgroepen')
        ->has('tariefgroepen')
    );
});

test('admin can create tariefgroep', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/financieel/tariefgroepen', [
        'naam' => 'Nieuw Tarief',
        'beschrijving' => 'Een nieuw tarief',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_tariefgroepen', ['naam' => 'Nieuw Tarief']);
});

test('admin can update tariefgroep', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $tariefgroep = Tariefgroep::first();

    $response = $this->actingAs($admin)->put("/admin/financieel/tariefgroepen/{$tariefgroep->id}", [
        'naam' => 'Bijgewerkt Tarief',
        'beschrijving' => 'Bijgewerkte beschrijving',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_tariefgroepen', [
        'id' => $tariefgroep->id,
        'naam' => 'Bijgewerkt Tarief',
    ]);
});

test('admin can delete tariefgroep', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $tariefgroep = Tariefgroep::create(['naam' => 'Te Verwijderen']);

    $response = $this->actingAs($admin)->delete("/admin/financieel/tariefgroepen/{$tariefgroep->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_tariefgroepen', ['id' => $tariefgroep->id]);
});

// --- Contributies ---

test('admin can view contributies', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/financieel/contributies');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/financieel/contributies')
        ->has('tariefgroepen')
        ->has('soortContributies')
        ->has('jaar')
    );
});

test('admin can create contributie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $tariefgroep = Tariefgroep::first();
    $soortContributie = SoortContributie::first();

    $response = $this->actingAs($admin)->post('/admin/financieel/contributies', [
        'tariefgroep_id' => $tariefgroep->id,
        'soort_contributie_id' => $soortContributie->id,
        'jaar' => 2026,
        'bedrag' => 100.00,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_contributies', [
        'tariefgroep_id' => $tariefgroep->id,
        'soort_contributie_id' => $soortContributie->id,
        'jaar' => 2026,
    ]);
});

test('admin can delete contributie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $tariefgroep = Tariefgroep::first();
    $soortContributie = SoortContributie::first();

    $contributie = Contributie::create([
        'tariefgroep_id' => $tariefgroep->id,
        'soort_contributie_id' => $soortContributie->id,
        'jaar' => 2026,
        'bedrag' => 50.00,
    ]);

    $response = $this->actingAs($admin)->delete("/admin/financieel/contributies/{$contributie->id}");

    $response->assertRedirect();
    $this->assertDatabaseMissing('soli_contributies', ['id' => $contributie->id]);
});

// --- Betalingen ---

test('admin can view betalingen', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/financieel/betalingen');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/financieel/betalingen')
        ->has('openstaand')
    );
});

test('admin can register betaling', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $tariefgroep = Tariefgroep::first();
    $soortContributie = SoortContributie::first();
    $relatie = Relatie::factory()->create();

    $contributie = Contributie::create([
        'tariefgroep_id' => $tariefgroep->id,
        'soort_contributie_id' => $soortContributie->id,
        'jaar' => 2026,
        'bedrag' => 100.00,
    ]);

    $teBetalen = TeBetakenContributie::create([
        'relatie_id' => $relatie->id,
        'contributie_id' => $contributie->id,
        'jaar' => 2026,
        'bedrag' => 100.00,
        'status' => 'open',
    ]);

    $response = $this->actingAs($admin)->post("/admin/financieel/betalingen/{$teBetalen->id}", [
        'bedrag' => 100.00,
        'datum' => '2026-03-01',
        'methode' => 'bank',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_betalingen', [
        'te_betalen_contributie_id' => $teBetalen->id,
        'datum' => '2026-03-01',
    ]);
    $this->assertDatabaseHas('soli_te_betalen_contributies', [
        'id' => $teBetalen->id,
        'status' => 'betaald',
    ]);
});

// --- Authorization ---

test('member cannot access financieel', function () {
    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get('/admin/financieel/tariefgroepen');
    $response->assertForbidden();
});

test('bestuur can view financieel', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');

    $response = $this->actingAs($bestuur)->get('/admin/financieel/tariefgroepen');
    $response->assertOk();
});

test('bestuur cannot create tariefgroep', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');

    $response = $this->actingAs($bestuur)->post('/admin/financieel/tariefgroepen', [
        'naam' => 'Verboden Tarief',
    ]);

    $response->assertForbidden();
});

test('guest is redirected to login', function () {
    $response = $this->get('/admin/financieel/tariefgroepen');
    $response->assertRedirect('/login');
});
