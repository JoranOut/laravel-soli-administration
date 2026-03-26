<?php

use App\Models\Email;
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

    config(['services.soli_sync.api_key' => 'test-sync-api-key']);
});

function syncHeaders(): array
{
    return ['X-API-Key' => 'test-sync-api-key'];
}

// --- Authentication ---

test('rejects request without API key', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
    ]);

    $response->assertStatus(401);
});

test('rejects request with invalid API key', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
    ], ['X-API-Key' => 'wrong-key']);

    $response->assertStatus(401);
});

test('accepts request with valid API key', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
    ], syncHeaders());

    $response->assertStatus(201);
});

// --- Validation ---

test('returns 422 for missing required fields', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [], syncHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['voornaam', 'achternaam', 'email']);
});

test('returns 422 for invalid email', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'not-an-email',
    ], syncHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['email']);
});

// --- Upsert: Create ---

test('creates new relatie with user account and lid type', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
    ], syncHeaders());

    $response->assertStatus(201);
    $response->assertJson(['status' => 'created']);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    expect($relatie)->not->toBeNull();
    expect($relatie->voornaam)->toBe('Jan');
    expect($relatie->achternaam)->toBe('Jansen');
    expect($relatie->actief)->toBeTrue();

    // Email created
    expect($relatie->emails()->where('email', 'jan@test.nl')->exists())->toBeTrue();

    // User created and linked
    expect($relatie->user_id)->not->toBeNull();
    $user = $relatie->user;
    expect($user->email)->toBe('jan@test.nl');
    expect($user->hasRole('member'))->toBeTrue();

    // Lid type attached
    $lidType = RelatieType::where('naam', 'lid')->first();
    expect($relatie->types()->where('relatie_type_id', $lidType->id)->exists())->toBeTrue();
});

test('creates relatie with tussenvoegsel', function () {
    $response = $this->putJson('/api/v1/sync/members/1001', [
        'voornaam' => 'Jan',
        'tussenvoegsel' => 'van der',
        'achternaam' => 'Berg',
        'email' => 'jan@test.nl',
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 1001)->first();
    expect($relatie->tussenvoegsel)->toBe('van der');
    expect($relatie->user->name)->toBe('Jan van der Berg');
});

test('creates relatie with onderdeel assignments', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'onderdeel_codes' => ['HA', 'BB'],
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    $activeOnderdelen = $relatie->onderdelen()->wherePivotNull('tot')->get();

    expect($activeOnderdelen)->toHaveCount(2);
    expect($activeOnderdelen->pluck('afkorting')->sort()->values()->toArray())->toBe(['BB', 'HA']);
});

test('warns about unknown onderdeel codes', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'onderdeel_codes' => ['HA', 'XX'],
    ], syncHeaders());

    $response->assertStatus(201);
    $response->assertJsonFragment(['Unknown onderdeel code: XX']);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    $activeOnderdelen = $relatie->onderdelen()->wherePivotNull('tot')->get();
    expect($activeOnderdelen)->toHaveCount(1);
});

test('links existing user to new relatie even when already linked to another relatie', function () {
    $existingUser = User::factory()->create(['email' => 'jan@test.nl']);
    $existingRelatie = Relatie::factory()->create(['user_id' => $existingUser->id]);

    $response = $this->putJson('/api/v1/sync/members/2000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
    ], syncHeaders());

    $response->assertStatus(201);
    $response->assertJson(['status' => 'created']);

    $newRelatie = Relatie::where('relatie_nummer', 2000)->first();
    expect($newRelatie->user_id)->toBe($existingUser->id);

    // Original relatie still linked
    $existingRelatie->refresh();
    expect($existingRelatie->user_id)->toBe($existingUser->id);
});

test('links existing unlinked user when creating relatie', function () {
    $existingUser = User::factory()->create(['email' => 'jan@test.nl']);

    $response = $this->putJson('/api/v1/sync/members/2000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 2000)->first();
    expect($relatie->user_id)->toBe($existingUser->id);
});

// --- Upsert: Update ---

test('updates existing relatie name fields', function () {
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
    ]);
    $relatie->emails()->create(['email' => 'jan@test.nl']);
    $user = User::factory()->create(['email' => 'jan@test.nl']);
    $user->assignRole('member');
    $relatie->update(['user_id' => $user->id]);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Pieter',
        'achternaam' => 'Pietersen',
        'email' => 'jan@test.nl',
    ], syncHeaders());

    $response->assertStatus(200);
    $response->assertJson(['status' => 'updated']);

    $relatie->refresh();
    expect($relatie->voornaam)->toBe('Pieter');
    expect($relatie->achternaam)->toBe('Pietersen');
});

test('adds missing email on update', function () {
    $relatie = Relatie::factory()->create(['relatie_nummer' => 1000]);
    $relatie->emails()->create(['email' => 'old@test.nl']);
    $user = User::factory()->create(['email' => 'old@test.nl']);
    $user->assignRole('member');
    $relatie->update(['user_id' => $user->id]);

    $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'new@test.nl',
    ], syncHeaders());

    expect($relatie->emails()->where('email', 'new@test.nl')->exists())->toBeTrue();
    expect($relatie->emails()->where('email', 'old@test.nl')->exists())->toBeTrue();
});

test('syncs onderdelen on update: adds new, closes removed', function () {
    $relatie = Relatie::factory()->create(['relatie_nummer' => 1000]);
    $relatie->emails()->create(['email' => 'jan@test.nl']);
    $user = User::factory()->create(['email' => 'jan@test.nl']);
    $user->assignRole('member');
    $relatie->update(['user_id' => $user->id]);

    $ha = Onderdeel::where('afkorting', 'HA')->first();
    $bb = Onderdeel::where('afkorting', 'BB')->first();
    $ko = Onderdeel::where('afkorting', 'KO')->first();

    // Start with HA and BB
    $relatie->onderdelen()->attach($ha->id, ['van' => now()->subYear()->toDateString()]);
    $relatie->onderdelen()->attach($bb->id, ['van' => now()->subYear()->toDateString()]);

    // Sync to HA and KO (remove BB, add KO)
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'jan@test.nl',
        'onderdeel_codes' => ['HA', 'KO'],
    ], syncHeaders());

    $response->assertStatus(200);

    $relatie->refresh();
    $activeOnderdelen = $relatie->onderdelen()->wherePivotNull('tot')->get();
    expect($activeOnderdelen->pluck('afkorting')->sort()->values()->toArray())->toBe(['HA', 'KO']);

    // BB should be closed (has tot date)
    $bbAssignment = $relatie->onderdelen()->where('onderdeel_id', $bb->id)->first();
    expect($bbAssignment->pivot->tot)->not->toBeNull();
});

test('reactivates inactive relatie on update', function () {
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'actief' => false,
    ]);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'jan@test.nl',
    ], syncHeaders());

    $response->assertStatus(200);

    $relatie->refresh();
    expect($relatie->actief)->toBeTrue();
    expect($relatie->user_id)->not->toBeNull();
});

test('creates user account for relatie without one on update', function () {
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'user_id' => null,
    ]);
    $relatie->emails()->create(['email' => 'jan@test.nl']);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'jan@test.nl',
    ], syncHeaders());

    $response->assertStatus(200);

    $relatie->refresh();
    expect($relatie->user_id)->not->toBeNull();
    expect($relatie->user->email)->toBe('jan@test.nl');
});

// --- Deactivate ---

test('deactivates member and deletes user account', function () {
    $user = User::factory()->create();
    $user->assignRole('member');
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'user_id' => $user->id,
        'actief' => true,
    ]);

    $lidType = RelatieType::where('naam', 'lid')->first();
    $relatie->types()->attach($lidType->id, ['van' => now()->subYear()->toDateString()]);

    $ha = Onderdeel::where('afkorting', 'HA')->first();
    $relatie->onderdelen()->attach($ha->id, ['van' => now()->subYear()->toDateString()]);

    $response = $this->deleteJson('/api/v1/sync/members/1000', [], syncHeaders());

    $response->assertStatus(200);
    $response->assertJson(['status' => 'deactivated']);

    $relatie->refresh();
    expect($relatie->actief)->toBeFalse();
    expect($relatie->user_id)->toBeNull();
    expect(User::find($user->id))->toBeNull();

    // Lid type closed
    $lidAssignment = $relatie->types()->where('relatie_type_id', $lidType->id)->first();
    expect($lidAssignment->pivot->tot)->not->toBeNull();

    // Onderdeel closed
    $haAssignment = $relatie->onderdelen()->where('onderdeel_id', $ha->id)->first();
    expect($haAssignment->pivot->tot)->not->toBeNull();
});

test('returns 404 when deactivating unknown member', function () {
    $response = $this->deleteJson('/api/v1/sync/members/9999', [], syncHeaders());

    $response->assertStatus(404);
});

test('deactivates member without user account gracefully', function () {
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'user_id' => null,
        'actief' => true,
    ]);

    $response = $this->deleteJson('/api/v1/sync/members/1000', [], syncHeaders());

    $response->assertStatus(200);

    $relatie->refresh();
    expect($relatie->actief)->toBeFalse();
});

// --- Edge Cases ---

test('handles empty onderdeel_codes array', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'onderdeel_codes' => [],
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    expect($relatie->onderdelen()->wherePivotNull('tot')->count())->toBe(0);
});

test('handles missing onderdeel_codes field', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    expect($relatie->onderdelen()->wherePivotNull('tot')->count())->toBe(0);
});

test('delete endpoint also requires API key', function () {
    $response = $this->deleteJson('/api/v1/sync/members/1000');

    $response->assertStatus(401);
});

// --- Reconcile ---

test('reconcile deactivates members not in active list', function () {
    $user1 = User::factory()->create();
    $user1->assignRole('member');
    $staying = Relatie::factory()->create(['relatie_nummer' => 1000, 'user_id' => $user1->id, 'actief' => true]);

    $user2 = User::factory()->create();
    $user2->assignRole('member');
    $leaving = Relatie::factory()->create(['relatie_nummer' => 1001, 'user_id' => $user2->id, 'actief' => true]);

    // Add extra members so deactivating 1 of 6 stays under 20% threshold
    for ($i = 1002; $i <= 1005; $i++) {
        Relatie::factory()->create(['relatie_nummer' => $i, 'actief' => true]);
    }

    $response = $this->postJson('/api/v1/sync/reconcile', [
        'active_lid_ids' => [1000, 1002, 1003, 1004, 1005],
    ], syncHeaders());

    $response->assertStatus(200);
    $response->assertJson([
        'status' => 'reconciled',
        'deactivated' => [1001],
        'deactivated_count' => 1,
    ]);

    $staying->refresh();
    expect($staying->actief)->toBeTrue();
    expect($staying->user_id)->not->toBeNull();

    $leaving->refresh();
    expect($leaving->actief)->toBeFalse();
    expect($leaving->user_id)->toBeNull();
    expect(User::find($user2->id))->toBeNull();
});

test('reconcile does nothing when all members are active', function () {
    Relatie::factory()->create(['relatie_nummer' => 1000, 'actief' => true]);
    Relatie::factory()->create(['relatie_nummer' => 1001, 'actief' => true]);

    $response = $this->postJson('/api/v1/sync/reconcile', [
        'active_lid_ids' => [1000, 1001],
    ], syncHeaders());

    $response->assertStatus(200);
    $response->assertJson(['deactivated_count' => 0]);
});

test('reconcile with empty list returns validation error', function () {
    $response = $this->postJson('/api/v1/sync/reconcile', [
        'active_lid_ids' => [],
    ], syncHeaders());

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('active_lid_ids');
});

test('reconcile aborts when exceeding 20 percent threshold', function () {
    // Create 5 active members
    for ($i = 1000; $i <= 1004; $i++) {
        Relatie::factory()->create(['relatie_nummer' => $i, 'actief' => true]);
    }

    // Keep only 3, deactivate 2 of 5 = 40% → should abort
    $response = $this->postJson('/api/v1/sync/reconcile', [
        'active_lid_ids' => [1000, 1001, 1002],
    ], syncHeaders());

    $response->assertStatus(409);
    $response->assertJsonFragment(['message' => 'Reconcile aborted: would deactivate 2 of 5 active members (exceeds 20% threshold).']);

    // All members should still be active
    for ($i = 1000; $i <= 1004; $i++) {
        expect(Relatie::where('relatie_nummer', $i)->first()->actief)->toBeTrue();
    }
});

test('reconcile ignores already inactive relaties', function () {
    Relatie::factory()->create(['relatie_nummer' => 1000, 'actief' => false]);
    Relatie::factory()->create(['relatie_nummer' => 1001, 'actief' => true]);

    $response = $this->postJson('/api/v1/sync/reconcile', [
        'active_lid_ids' => [1001],
    ], syncHeaders());

    $response->assertStatus(200);
    $response->assertJson(['deactivated_count' => 0]);
});

test('reconcile requires API key', function () {
    $response = $this->postJson('/api/v1/sync/reconcile', [
        'active_lid_ids' => [1000],
    ]);

    $response->assertStatus(401);
});

test('reconcile validates active_lid_ids is required', function () {
    $response = $this->postJson('/api/v1/sync/reconcile', [], syncHeaders());

    $response->assertStatus(422);
});
