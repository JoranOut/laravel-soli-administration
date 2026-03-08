<?php

use App\Models\Instrument;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('admin can view instrumenten index', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Instrument::factory(3)->create();

    $response = $this->actingAs($admin)->get('/admin/instrumenten');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/instrumenten/index')
        ->has('instrumenten.data', 3)
    );
});

test('admin can search instrumenten', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Instrument::factory()->create(['nummer' => 'I-1234', 'soort' => 'Trompet']);
    Instrument::factory()->create(['nummer' => 'I-5678', 'soort' => 'Klarinet']);

    $response = $this->actingAs($admin)->get('/admin/instrumenten?search=Trompet');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('instrumenten.data', 1)
    );
});

test('admin can create instrument', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/instrumenten', [
        'nummer' => 'I-9999',
        'soort' => 'Trompet',
        'merk' => 'Yamaha',
        'status' => 'beschikbaar',
        'eigendom' => 'soli',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrumenten', [
        'nummer' => 'I-9999',
        'soort' => 'Trompet',
    ]);
});

test('admin can view instrument detail', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $instrument = Instrument::factory()->create();

    $response = $this->actingAs($admin)->get("/admin/instrumenten/{$instrument->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/instrumenten/show')
        ->has('instrument')
        ->has('relaties')
    );
});

test('admin can update instrument', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $instrument = Instrument::factory()->create();

    $response = $this->actingAs($admin)->put("/admin/instrumenten/{$instrument->id}", [
        'nummer' => $instrument->nummer,
        'soort' => 'Saxofoon',
        'merk' => 'Selmer',
        'status' => 'beschikbaar',
        'eigendom' => 'soli',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_instrumenten', [
        'id' => $instrument->id,
        'soort' => 'Saxofoon',
        'merk' => 'Selmer',
    ]);
});

test('admin can delete instrument', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $instrument = Instrument::factory()->create();

    $response = $this->actingAs($admin)->delete("/admin/instrumenten/{$instrument->id}");

    $response->assertRedirect('/admin/instrumenten');
    $this->assertSoftDeleted('soli_instrumenten', ['id' => $instrument->id]);
});

test('member cannot access instrumenten', function () {
    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get('/admin/instrumenten');
    $response->assertForbidden();
});

test('bestuur can view instrumenten', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    Instrument::factory()->create();

    $response = $this->actingAs($bestuur)->get('/admin/instrumenten');
    $response->assertOk();
});

test('guest is redirected to login', function () {
    $response = $this->get('/admin/instrumenten');
    $response->assertRedirect('/login');
});
