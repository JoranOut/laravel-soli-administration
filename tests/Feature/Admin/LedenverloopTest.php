<?php

use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\User;
use Database\Seeders\OnderdeelSeeder;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RelatieTypeSeeder::class);
    $this->seed(OnderdeelSeeder::class);
    $this->withoutVite();
});

test('admin can view ledenverloop page', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $lidType = RelatieType::where('naam', 'lid')->first();

    // Create a relatie that joined
    $joined = Relatie::factory()->create();
    $joined->types()->attach($lidType->id, [
        'van' => now()->subYears(2)->toDateString(),
    ]);

    // Create a relatie that left
    $left = Relatie::factory()->create();
    $left->types()->attach($lidType->id, [
        'van' => now()->subYears(5)->toDateString(),
        'tot' => now()->subYears(1)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get('/admin/ledenverloop');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/ledenverloop/index')
        ->has('joined.data', 2) // both relaties have a van date
        ->has('left.data', 1)   // only the left relatie has a tot date
        ->where('tab', 'joined')
    );
});

test('tab parameter is passed through', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/ledenverloop?tab=left');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('tab', 'left')
    );
});

test('joined relaties have onderdelen loaded', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $lidType = RelatieType::where('naam', 'lid')->first();
    $onderdeel = Onderdeel::first();

    $relatie = Relatie::factory()->create();
    $relatie->types()->attach($lidType->id, [
        'van' => now()->subYears(2)->toDateString(),
    ]);
    $relatie->onderdelen()->attach($onderdeel->id, [
        'van' => now()->subYears(2)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get('/admin/ledenverloop');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('joined.data', 1)
        ->has('joined.data.0.onderdelen', 1)
        ->where('joined.data.0.onderdelen.0.naam', $onderdeel->naam)
    );
});

test('left relaties appear in left tab', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $lidType = RelatieType::where('naam', 'lid')->first();

    $relatie = Relatie::factory()->create();
    $relatie->types()->attach($lidType->id, [
        'van' => now()->subYears(5)->toDateString(),
        'tot' => now()->subYears(1)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get('/admin/ledenverloop');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('joined.data', 1) // relatie still has a van date
        ->has('left.data', 1)
        ->where('left.data.0.lid_datum', now()->subYears(1)->toDateString())
    );
});

test('donateur type does not appear in ledenverloop', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $donateurType = RelatieType::where('naam', 'donateur')->first();

    $relatie = Relatie::factory()->create();
    $relatie->types()->attach($donateurType->id, [
        'van' => now()->subYears(1)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get('/admin/ledenverloop');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('joined.data', 0)
        ->has('left.data', 0)
    );
});

test('bestuur can view ledenverloop page', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');

    $response = $this->actingAs($bestuur)->get('/admin/ledenverloop');

    $response->assertOk();
});

test('guest is redirected to login', function () {
    $response = $this->get('/admin/ledenverloop');

    $response->assertRedirect('/login');
});
