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
    $this->withoutVite();
});

test('admin can view relaties index', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Relatie::factory(3)->create();

    $response = $this->actingAs($admin)->get('/admin/relaties');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/index')
        ->has('relaties.data', 3)
        ->has('relatieTypes')
    );
});

test('admin can view relaties index with emails', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $relatie->emails()->create(['email' => 'piet@example.com']);

    $response = $this->actingAs($admin)->get('/admin/relaties');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/index')
        ->has('relaties.data', 1)
        ->where('relaties.data.0.emails.0.email', 'piet@example.com')
    );
});

test('relaties index includes emails for relatie with accented name', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create([
        'voornaam' => 'René',
        'tussenvoegsel' => null,
        'achternaam' => 'Müller',
    ]);
    $relatie->emails()->create(['email' => 'rene@example.com']);

    $response = $this->actingAs($admin)->get('/admin/relaties');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/index')
        ->has('relaties.data', 1)
        ->where('relaties.data.0.emails.0.email', 'rene@example.com')
        ->where('relaties.data.0.volledige_naam', 'René Müller')
    );
});

test('admin can search relaties', function () {
    $admin = User::factory()->create()->assignRole('admin');
    Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);
    Relatie::factory()->create(['voornaam' => 'Piet', 'achternaam' => 'Pietersen']);

    $response = $this->actingAs($admin)->get('/admin/relaties?search=Jan');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('relaties.data', 1)
    );
});

test('admin can filter relaties by type', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $lidType = RelatieType::where('naam', 'lid')->first();
    $donateurType = RelatieType::where('naam', 'donateur')->first();

    $lid = Relatie::factory()->create();
    $lid->types()->attach($lidType->id, ['van' => now()->subYear()]);

    $donateur = Relatie::factory()->create();
    $donateur->types()->attach($donateurType->id, ['van' => now()->subYear()]);

    $response = $this->actingAs($admin)->get('/admin/relaties?type=lid');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('relaties.data', 1)
    );
});

test('admin can create a relatie', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/relaties/create');
    $response->assertOk();

    $response = $this->actingAs($admin)->post('/admin/relaties', [
        'relatie_nummer' => 9999,
        'voornaam' => 'Test',
        'achternaam' => 'Persoon',
        'geslacht' => 'M',
        'emails' => [
            ['email' => 'test@example.com'],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relaties', [
        'relatie_nummer' => 9999,
        'voornaam' => 'Test',
        'achternaam' => 'Persoon',
    ]);

    $relatie = Relatie::where('relatie_nummer', 9999)->first();
    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('member'))->toBeTrue();
    expect($relatie->user_id)->toBe($user->id);
});

test('admin can view a relatie detail with all props', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $linkedUser = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);

    $response = $this->actingAs($admin)->get("/admin/relaties/{$relatie->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/show')
        ->has('relatie')
        ->has('relatie.user')
        ->has('relatieTypes')
        ->has('onderdelen')
        ->has('instrumentSoorten')
        ->has('users')
    );
});

test('bestuur does not receive edit props on relatie show', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    $linkedUser = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);

    $response = $this->actingAs($bestuur)->get("/admin/relaties/{$relatie->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/show')
        ->has('relatie')
        ->missing('relatie.user')
        ->missing('relatieTypes')
        ->missing('onderdelen')
        ->missing('instrumentSoorten')
        ->missing('users')
    );
});

test('member does not receive edit props on own relatie show', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $member->id]);

    $response = $this->actingAs($member)->get("/admin/relaties/{$relatie->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/show')
        ->has('relatie')
        ->missing('relatie.user')
        ->missing('relatieTypes')
        ->missing('onderdelen')
        ->missing('instrumentSoorten')
        ->missing('users')
    );
});

test('admin can update a relatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}", [
        'voornaam' => 'Updated',
        'achternaam' => 'Naam',
        'geslacht' => 'V',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relaties', [
        'id' => $relatie->id,
        'voornaam' => 'Updated',
        'achternaam' => 'Naam',
    ]);
});

test('admin can delete a relatie', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}");

    $response->assertRedirect('/admin/relaties');
    $this->assertSoftDeleted('soli_relaties', ['id' => $relatie->id]);
});

test('member with linked relatie is redirected from index to own relatie', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $member->id]);

    $response = $this->actingAs($member)->get('/admin/relaties');
    $response->assertRedirect("/admin/relaties/{$relatie->id}");
});

test('member without linked relatie sees not-linked page', function () {
    $this->withoutVite();
    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get('/admin/relaties');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/relaties/not-linked'));
});

test('member can view own relatie', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $member->id]);

    $response = $this->actingAs($member)->get("/admin/relaties/{$relatie->id}");
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/relaties/show'));
});

test('member cannot view other relatie', function () {
    $member = User::factory()->create()->assignRole('member');
    Relatie::factory()->create(['user_id' => $member->id]);
    $otherRelatie = Relatie::factory()->create();

    $response = $this->actingAs($member)->get("/admin/relaties/{$otherRelatie->id}");
    $response->assertForbidden();
});

test('member cannot create relaties', function () {
    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get('/admin/relaties/create');
    $response->assertForbidden();
});

test('member cannot update relaties', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($member)->put("/admin/relaties/{$relatie->id}", [
        'voornaam' => 'Hack',
        'achternaam' => 'Attempt',
        'geslacht' => 'M',
    ]);

    $response->assertForbidden();
});

test('member cannot delete relaties', function () {
    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create();

    $response = $this->actingAs($member)->delete("/admin/relaties/{$relatie->id}");
    $response->assertForbidden();
});

test('guest is redirected to login', function () {
    $response = $this->get('/admin/relaties');
    $response->assertRedirect('/login');
});

test('relatie-types API requires relaties.view permission', function () {
    $user = User::factory()->create(); // no roles, no permissions

    $this->actingAs($user)
        ->getJson('/admin/relatie-types')
        ->assertForbidden();
});

test('relatie-types API returns types for authorized users', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');

    $response = $this->actingAs($bestuur)
        ->getJson('/admin/relatie-types');

    $response->assertOk();
    $response->assertJsonStructure([['id', 'naam']]);
});

test('guest cannot access relatie-types API', function () {
    $this->getJson('/admin/relatie-types')
        ->assertUnauthorized();
});

test('bestuur can view relaties', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');
    Relatie::factory()->create();

    $response = $this->actingAs($bestuur)->get('/admin/relaties');
    $response->assertOk();
});

test('bestuur cannot create relaties', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');

    $response = $this->actingAs($bestuur)->post('/admin/relaties', [
        'relatie_nummer' => 8888,
        'voornaam' => 'Bestuur',
        'achternaam' => 'Test',
        'geslacht' => 'O',
        'emails' => [
            ['email' => 'bestuur-test@example.com'],
        ],
    ]);

    $response->assertForbidden();
});

test('ledenadministratie can create relaties', function () {
    $ledenadmin = User::factory()->create()->assignRole('ledenadministratie');

    $response = $this->actingAs($ledenadmin)->post('/admin/relaties', [
        'relatie_nummer' => 8888,
        'voornaam' => 'Ledenadmin',
        'achternaam' => 'Test',
        'geslacht' => 'O',
        'emails' => [
            ['email' => 'ledenadmin-test@example.com'],
        ],
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('soli_relaties', ['relatie_nummer' => 8888]);
});

// --- Wizard / multi-step store tests ---

test('admin can create relatie with all sub-resources', function () {
    $this->seed(OnderdeelSeeder::class);
    $admin = User::factory()->create()->assignRole('admin');
    $lidType = RelatieType::where('naam', 'lid')->first();
    $onderdeel = Onderdeel::where('naam', 'Harmonie orkest')->first();

    $response = $this->actingAs($admin)->post('/admin/relaties', [
        'relatie_nummer' => 7777,
        'voornaam' => 'Wizard',
        'achternaam' => 'Test',
        'geslacht' => 'V',
        'geboortedatum' => '1990-05-15',
        'geboorteplaats' => 'Driehuis',
        'nationaliteit' => 'Nederlandse',
        'types' => [
            ['type_id' => $lidType->id, 'van' => '2026-01-01'],
        ],
        'adressen' => [
            [
                'straat' => 'Hoofdstraat',
                'huisnummer' => '10',
                'huisnummer_toevoeging' => 'A',
                'postcode' => '1985AB',
                'plaats' => 'Driehuis',
                'land' => 'Nederland',
            ],
        ],
        'emails' => [
            ['email' => 'wizard@test.nl'],
        ],
        'telefoons' => [
            ['nummer' => '0612345678'],
        ],
        'giro_gegevens' => [
            [
                'iban' => 'NL91ABNA0417164300',
                'tenaamstelling' => 'W. Test',
                'machtiging' => true,
            ],
        ],
        'lidmaatschappen' => [
            ['lid_sinds' => '2026-01-01'],
        ],
        'onderdelen' => [
            ['onderdeel_id' => $onderdeel->id, 'functie' => 'Muzikant', 'van' => '2026-01-01'],
        ],
        'opleidingen' => [
            ['naam' => 'HaFaBra A', 'instituut' => 'Muziekschool', 'instrument' => 'Trompet'],
        ],
    ]);

    $response->assertRedirect();

    $relatie = Relatie::where('relatie_nummer', 7777)->first();
    expect($relatie)->not->toBeNull();

    $this->assertDatabaseHas('soli_relatie_relatie_type', [
        'relatie_id' => $relatie->id,
        'relatie_type_id' => $lidType->id,
    ]);
    $this->assertDatabaseHas('soli_adressen', [
        'relatie_id' => $relatie->id,
        'straat' => 'Hoofdstraat',
    ]);
    $this->assertDatabaseHas('soli_emails', [
        'relatie_id' => $relatie->id,
        'email' => 'wizard@test.nl',
    ]);
    $this->assertDatabaseHas('soli_telefoons', [
        'relatie_id' => $relatie->id,
        'nummer' => '0612345678',
    ]);
    $this->assertDatabaseHas('soli_giro_gegevens', [
        'relatie_id' => $relatie->id,
        'iban' => 'NL91ABNA0417164300',
    ]);
    $this->assertDatabaseHas('soli_relatie_sinds', [
        'relatie_id' => $relatie->id,
        'lid_sinds' => '2026-01-01',
    ]);
    $this->assertDatabaseHas('soli_relatie_onderdeel', [
        'relatie_id' => $relatie->id,
        'onderdeel_id' => $onderdeel->id,
        'functie' => 'Muzikant',
    ]);
    $this->assertDatabaseHas('soli_opleidingen', [
        'relatie_id' => $relatie->id,
        'naam' => 'HaFaBra A',
    ]);

    // Verify user account was created
    $user = User::where('email', 'wizard@test.nl')->first();
    expect($user)->not->toBeNull();
    expect($user->hasRole('member'))->toBeTrue();
    expect($relatie->user_id)->toBe($user->id);
});

test('store validates nested sub-resource fields', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/relaties', [
        'relatie_nummer' => 6666,
        'voornaam' => 'Validation',
        'achternaam' => 'Test',
        'geslacht' => 'O',
        'adressen' => [
            [], // missing required fields
        ],
        'emails' => [
            ['email' => 'not-an-email'], // invalid email
        ],
    ]);

    $response->assertSessionHasErrors([
        'adressen.0.straat',
        'adressen.0.huisnummer',
        'adressen.0.postcode',
        'adressen.0.plaats',
        'adressen.0.land',
        'emails.0.email',
    ]);
});

test('store rolls back on failure within transaction', function () {
    $admin = User::factory()->create()->assignRole('admin');

    // Use a non-existent type_id to trigger an integrity constraint error
    $response = $this->actingAs($admin)->post('/admin/relaties', [
        'relatie_nummer' => 5555,
        'voornaam' => 'Rollback',
        'achternaam' => 'Test',
        'geslacht' => 'M',
        'emails' => [
            ['email' => 'rollback@example.com'],
        ],
        'types' => [
            ['type_id' => 99999, 'van' => '2026-01-01'],
        ],
    ]);

    $response->assertSessionHasErrors('types.0.type_id');
    $this->assertDatabaseMissing('soli_relaties', ['relatie_nummer' => 5555]);
    $this->assertDatabaseMissing('users', ['email' => 'rollback@example.com']);
});

test('create page receives preselected type id', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/relaties/create?type=lid');

    $lidType = RelatieType::where('naam', 'lid')->first();

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/create')
        ->where('preselectedTypeId', $lidType->id)
    );
});

test('create page works without type param', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/relaties/create');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/create')
        ->where('preselectedTypeId', null)
    );
});

test('create page passes onderdelen', function () {
    $this->seed(OnderdeelSeeder::class);
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/relaties/create');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('onderdelen')
    );
});

// --- User account creation tests ---

test('store requires at least one email', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/relaties', [
        'relatie_nummer' => 4444,
        'voornaam' => 'No',
        'achternaam' => 'Email',
        'geslacht' => 'O',
        'emails' => [],
    ]);

    $response->assertSessionHasErrors('emails');
});

test('store rejects email already used by existing user', function () {
    $admin = User::factory()->create()->assignRole('admin');
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->actingAs($admin)->post('/admin/relaties', [
        'relatie_nummer' => 3333,
        'voornaam' => 'Duplicate',
        'achternaam' => 'Email',
        'geslacht' => 'O',
        'emails' => [
            ['email' => 'taken@example.com'],
        ],
    ]);

    $response->assertSessionHasErrors('emails.0.email');
});

test('store creates user with member role linked to relatie', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post('/admin/relaties', [
        'relatie_nummer' => 2222,
        'voornaam' => 'Jan',
        'tussenvoegsel' => 'van',
        'achternaam' => 'Berg',
        'geslacht' => 'M',
        'emails' => [
            ['email' => 'jan@example.com'],
        ],
    ]);

    $response->assertRedirect();

    $relatie = Relatie::where('relatie_nummer', 2222)->first();
    $user = User::where('email', 'jan@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Jan van Berg');
    expect($user->hasRole('member'))->toBeTrue();
    expect($relatie->user_id)->toBe($user->id);
});

test('store rolls back user creation on transaction failure', function () {
    $admin = User::factory()->create()->assignRole('admin');

    // Force a DB error by using a non-existent type_id (fails validation)
    $response = $this->actingAs($admin)->post('/admin/relaties', [
        'relatie_nummer' => 1111,
        'voornaam' => 'Rollback',
        'achternaam' => 'User',
        'geslacht' => 'M',
        'emails' => [
            ['email' => 'rollback-user@example.com'],
        ],
        'types' => [
            ['type_id' => 99999, 'van' => '2026-01-01'],
        ],
    ]);

    $response->assertSessionHasErrors('types.0.type_id');
    $this->assertDatabaseMissing('soli_relaties', ['relatie_nummer' => 1111]);
    $this->assertDatabaseMissing('users', ['email' => 'rollback-user@example.com']);
});
