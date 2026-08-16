<?php

use App\Models\InstrumentFamilie;
use App\Models\InstrumentSoort;
use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieInstrument;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('userinfo returns sub for openid scope', function () {
    $user = User::factory()->create();

    Passport::actingAs($user, ['openid']);

    $response = $this->getJson('/api/oauth/userinfo');

    $response->assertOk();
    $response->assertJsonFragment(['sub' => (string) $user->id]);
});

test('userinfo returns profile claims for profile scope', function () {
    $user = User::factory()->create(['name' => 'Jan Jansen']);
    $relatie = Relatie::factory()->create([
        'user_id' => $user->id,
        'voornaam' => 'Jan',
        'tussenvoegsel' => null,
        'achternaam' => 'Jansen',
    ]);

    Passport::actingAs($user, ['openid', 'profile']);

    $response = $this->getJson('/api/oauth/userinfo');

    $response->assertOk();
    $response->assertJsonFragment([
        'name' => 'Jan Jansen',
        'preferred_username' => 'Jan Jansen',
        'given_name' => 'Jan',
        'family_name' => 'Jansen',
    ]);
});

test('userinfo returns preferred_username with tussenvoegsel', function () {
    $user = User::factory()->create(['name' => 'Jan van der Berg']);
    $relatie = Relatie::factory()->create([
        'user_id' => $user->id,
        'voornaam' => 'Jan',
        'tussenvoegsel' => 'van der',
        'achternaam' => 'Berg',
    ]);

    Passport::actingAs($user, ['openid', 'profile']);

    $response = $this->getJson('/api/oauth/userinfo');

    $response->assertOk();
    $response->assertJsonFragment([
        'preferred_username' => 'Jan van der Berg',
    ]);
});

test('userinfo returns email claims for email scope', function () {
    $user = User::factory()->create([
        'email' => 'jan@soli.nl',
        'email_verified_at' => now(),
    ]);

    Passport::actingAs($user, ['openid', 'email']);

    $response = $this->getJson('/api/oauth/userinfo');

    $response->assertOk();
    $response->assertJsonFragment([
        'email' => 'jan@soli.nl',
        'email_verified' => true,
    ]);
});

test('userinfo returns roles for roles scope', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    Passport::actingAs($user, ['openid', 'roles']);

    $response = $this->getJson('/api/oauth/userinfo');

    $response->assertOk();
    $response->assertJsonFragment(['roles' => ['admin']]);
});

test('userinfo returns assignments with onderdeel name and slug for assignments scope', function () {
    $user = User::factory()->create();
    $relatie = Relatie::factory()->create(['user_id' => $user->id]);
    $onderdeel = Onderdeel::factory()->create(['naam' => 'Klein Orkest']);
    $relatie->onderdelen()->attach($onderdeel->id, ['van' => now()->subYear()->toDateString()]);

    $familie = InstrumentFamilie::create(['naam' => 'Slagwerk']);
    $soort = InstrumentSoort::create([
        'naam' => 'Melodisch slagwerk',
        'instrument_familie_id' => $familie->id,
    ]);
    RelatieInstrument::create([
        'relatie_id' => $relatie->id,
        'onderdeel_id' => $onderdeel->id,
        'instrument_soort_id' => $soort->id,
    ]);

    Passport::actingAs($user, ['openid', 'assignments']);

    $response = $this->getJson('/api/oauth/userinfo');

    $response->assertOk();
    $response->assertJsonFragment([
        'assignments' => [
            [
                'onderdeel_id' => $onderdeel->id,
                'onderdeel' => 'Klein Orkest',
                'onderdeel_slug' => 'klein-orkest',
                'instrument_soort_id' => $soort->id,
                'instrument_soort' => 'Melodisch slagwerk',
                'instrument_familie' => 'Slagwerk',
            ],
        ],
    ]);
});

test('userinfo does not leak claims for ungranted scopes', function () {
    $user = User::factory()->create(['email' => 'jan@soli.nl']);

    Passport::actingAs($user, ['openid']);

    $response = $this->getJson('/api/oauth/userinfo');

    $response->assertOk();
    $response->assertJsonMissing(['email' => 'jan@soli.nl']);
    $response->assertJsonMissing(['roles']);
    $response->assertJsonMissing(['name']);
});

test('userinfo rejects unauthenticated request', function () {
    $response = $this->getJson('/api/oauth/userinfo');

    $response->assertStatus(401);
});
