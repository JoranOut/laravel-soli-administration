<?php

use App\Models\Relatie;
use App\Models\User;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('contact'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can view the contact page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('contact'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('contact'));
});

test('contact page shows bestuur members with pivot functie and email', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RelatieTypeSeeder::class);

    $user = User::factory()->create()->assignRole('member');

    $bestuurType = \App\Models\RelatieType::where('naam', 'bestuur')->first();

    $bestuurRelatie = Relatie::factory()->create([
        'voornaam' => 'Jan',
        'tussenvoegsel' => null,
        'achternaam' => 'Bestuurder',
    ]);
    $bestuurRelatie->types()->attach($bestuurType->id, [
        'van' => now()->subYear(),
        'tot' => null,
        'functie' => 'Voorzitter',
        'email' => 'voorzitter@soli.nl',
    ]);

    $response = $this->actingAs($user)->get(route('contact'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('contact')
        ->has('bestuur', 1)
        ->where('bestuur.0.volledige_naam', 'Jan Bestuurder')
        ->where('bestuur.0.functie', 'Voorzitter')
        ->where('bestuur.0.email', 'voorzitter@soli.nl')
    );
});
