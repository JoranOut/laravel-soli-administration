<?php

use App\Models\Relatie;
use App\Models\User;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('member with linked relatie sees relatie data on dashboard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RelatieTypeSeeder::class);

    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $member->id]);

    $response = $this->actingAs($member)->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/show')
        ->has('relatie')
        ->where('relatie.id', $relatie->id)
    );
});

test('member without linked relatie sees not-linked page on dashboard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/not-linked')
    );
});