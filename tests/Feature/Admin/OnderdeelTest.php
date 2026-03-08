<?php

use App\Models\Onderdeel;
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
        'type' => 'orkest',
        'beschrijving' => 'Het hoofdorkest',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_onderdelen', [
        'naam' => 'Harmonie orkest',
        'type' => 'orkest',
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
        'type' => 'ensemble',
        'beschrijving' => 'Nieuwe beschrijving',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_onderdelen', [
        'id' => $onderdeel->id,
        'naam' => 'Bijgewerkt Orkest',
        'type' => 'ensemble',
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

test('guest is redirected to login', function () {
    $response = $this->get('/admin/onderdelen');
    $response->assertRedirect('/login');
});
