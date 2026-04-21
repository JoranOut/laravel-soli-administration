<?php

use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin can view onderdelen index', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Onderdeel::factory(3)->create();

    $response = $this->actingAs($admin)->get('/admin/onderdelen');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/onderdelen/index')
        ->has('onderdelen', 3)
    );
});

test('admin can create onderdeel', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/onderdelen', [
        'naam' => 'Harmonie orkest',
        'type' => 'muziekgroep',
        'beschrijving' => 'Het hoofdorkest',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_onderdelen', [
        'naam' => 'Harmonie orkest',
        'type' => 'muziekgroep',
    ]);
});

test('admin can view onderdeel detail', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $onderdeel = Onderdeel::factory()->create();

    $response = $this->actingAs($admin)->get("/admin/onderdelen/{$onderdeel->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/onderdelen/show')
        ->has('onderdeel')
    );
});

test('admin can update onderdeel', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $onderdeel = Onderdeel::factory()->create();

    $response = $this->actingAs($admin)->put("/admin/onderdelen/{$onderdeel->id}", [
        'naam' => 'Bijgewerkt Orkest',
        'type' => 'commissie',
        'beschrijving' => 'Nieuwe beschrijving',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_onderdelen', [
        'id' => $onderdeel->id,
        'naam' => 'Bijgewerkt Orkest',
        'type' => 'commissie',
    ]);
});

test('admin can delete onderdeel', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $onderdeel = Onderdeel::factory()->create();

    $response = $this->actingAs($admin)->delete("/admin/onderdelen/{$onderdeel->id}");

    $response->assertRedirect('/admin/onderdelen');
    $this->assertSoftDeleted('soli_onderdelen', ['id' => $onderdeel->id]);
});

test('member cannot access onderdelen', function () {
    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get('/admin/onderdelen');
    $response->assertForbidden();
});

test('bestuur can view onderdelen', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    Onderdeel::factory()->create();

    $response = $this->actingAs($bestuur)->get('/admin/onderdelen');
    $response->assertOk();
});

test('bestuur cannot create onderdelen', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');

    $response = $this->actingAs($bestuur)->post('/admin/onderdelen', [
        'naam' => 'Bestuur Orkest',
        'type' => 'commissie',
    ]);

    $response->assertForbidden();
});

test('ledenadministratie can create onderdelen', function () {
    $ledenadmin = User::factory()->create()->assignRole('ledenadministratie');

    $response = $this->actingAs($ledenadmin)->post('/admin/onderdelen', [
        'naam' => 'Ledenadmin Orkest',
        'type' => 'commissie',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_onderdelen', ['naam' => 'Ledenadmin Orkest']);
});

test('admin can create onderdeel with afkorting', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/onderdelen', [
        'naam' => 'Harmonie',
        'afkorting' => 'HA',
        'type' => 'muziekgroep',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_onderdelen', [
        'naam' => 'Harmonie',
        'afkorting' => 'HA',
    ]);
});

test('admin can create onderdeel without afkorting', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/onderdelen', [
        'naam' => 'Commissie X',
        'type' => 'commissie',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_onderdelen', [
        'naam' => 'Commissie X',
        'afkorting' => null,
    ]);
});

test('admin can update onderdeel afkorting', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $onderdeel = Onderdeel::factory()->create();

    $response = $this->actingAs($admin)->put("/admin/onderdelen/{$onderdeel->id}", [
        'naam' => $onderdeel->naam,
        'afkorting' => 'KO',
        'type' => $onderdeel->type,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_onderdelen', [
        'id' => $onderdeel->id,
        'afkorting' => 'KO',
    ]);
});

test('afkorting must be unique on create', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Onderdeel::factory()->create(['afkorting' => 'HA']);

    $response = $this->actingAs($admin)->post('/admin/onderdelen', [
        'naam' => 'Duplicate',
        'afkorting' => 'HA',
        'type' => 'muziekgroep',
    ]);

    $response->assertSessionHasErrors('afkorting');
});

test('updating onderdeel can keep its own afkorting', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $onderdeel = Onderdeel::factory()->create(['afkorting' => 'MO']);

    $response = $this->actingAs($admin)->put("/admin/onderdelen/{$onderdeel->id}", [
        'naam' => 'Updated Name',
        'afkorting' => 'MO',
        'type' => $onderdeel->type,
    ]);

    $response->assertRedirect();
    $response->assertSessionDoesntHaveErrors('afkorting');
    $this->assertDatabaseHas('soli_onderdelen', [
        'id' => $onderdeel->id,
        'naam' => 'Updated Name',
        'afkorting' => 'MO',
    ]);
});

test('afkorting cannot exceed 10 characters', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/onderdelen', [
        'naam' => 'Long Afkorting',
        'afkorting' => 'ABCDEFGHIJK',
        'type' => 'muziekgroep',
    ]);

    $response->assertSessionHasErrors('afkorting');
});

test('onderdeel show includes emails for relaties', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $onderdeel = Onderdeel::factory()->create();
    $relatie = Relatie::factory()->create();
    $relatie->onderdelen()->attach($onderdeel->id, ['van' => now()->subYear()->toDateString()]);
    $relatie->emails()->create(['email' => 'jan@example.com']);

    $response = $this->actingAs($admin)->get("/admin/onderdelen/{$onderdeel->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/onderdelen/show')
        ->has('onderdeel.relaties', 1)
        ->where('onderdeel.relaties.0.emails.0.email', 'jan@example.com')
    );
});

test('onderdeel show includes emails for relatie with accented name', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $onderdeel = Onderdeel::factory()->create();
    $relatie = Relatie::factory()->create([
        'voornaam' => 'René',
        'tussenvoegsel' => null,
        'achternaam' => 'Müller',
    ]);
    $relatie->onderdelen()->attach($onderdeel->id, ['van' => now()->subYear()->toDateString()]);
    $relatie->emails()->create(['email' => 'rene.muller@example.com']);

    $response = $this->actingAs($admin)->get("/admin/onderdelen/{$onderdeel->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/onderdelen/show')
        ->has('onderdeel.relaties', 1)
        ->where('onderdeel.relaties.0.emails.0.email', 'rene.muller@example.com')
        ->where('onderdeel.relaties.0.volledige_naam', 'René Müller')
    );
});

test('guest is redirected to login', function () {
    $response = $this->get('/admin/onderdelen');
    $response->assertRedirect('/login');
});
