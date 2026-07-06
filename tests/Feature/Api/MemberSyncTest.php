<?php

use App\Models\Email;
use App\Models\InstrumentSoort;
use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieInstrument;
use App\Models\RelatieType;
use App\Models\User;
use Database\Seeders\InstrumentSoortSeeder;
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

test('syncs user email when sync email changes', function () {
    $user = User::factory()->create(['email' => 'old@test.nl']);
    $user->assignRole('member');
    $relatie = Relatie::factory()->create(['relatie_nummer' => 1000, 'user_id' => $user->id]);
    $relatie->emails()->create(['email' => 'old@test.nl']);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'new@test.nl',
    ], syncHeaders());

    $response->assertStatus(200);

    $user->refresh();
    expect($user->email)->toBe('new@test.nl');
    expect($user->email_verified_at)->toBeNull();
});

test('does not overwrite user email if new email is taken by another user', function () {
    $otherUser = User::factory()->create(['email' => 'taken@test.nl']);

    $user = User::factory()->create(['email' => 'old@test.nl']);
    $user->assignRole('member');
    $relatie = Relatie::factory()->create(['relatie_nummer' => 1000, 'user_id' => $user->id]);
    $relatie->emails()->create(['email' => 'old@test.nl']);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'taken@test.nl',
    ], syncHeaders());

    $response->assertStatus(200);

    // User email should NOT have changed
    $user->refresh();
    expect($user->email)->toBe('old@test.nl');
});

test('does not touch user email when sync email is unchanged', function () {
    $user = User::factory()->create(['email' => 'same@test.nl', 'email_verified_at' => now()]);
    $user->assignRole('member');
    $relatie = Relatie::factory()->create(['relatie_nummer' => 1000, 'user_id' => $user->id]);
    $relatie->emails()->create(['email' => 'same@test.nl']);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'same@test.nl',
    ], syncHeaders());

    $response->assertStatus(200);

    $user->refresh();
    expect($user->email)->toBe('same@test.nl');
    expect($user->email_verified_at)->not->toBeNull();
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

test('sync does not close assignment to admin-managed onderdeel', function () {
    $relatie = Relatie::factory()->create(['relatie_nummer' => 1000]);
    $relatie->emails()->create(['email' => 'jan@test.nl']);
    $user = User::factory()->create(['email' => 'jan@test.nl']);
    $user->assignRole('member');
    $relatie->update(['user_id' => $user->id]);

    $ha = Onderdeel::where('afkorting', 'HA')->first();
    $bb = Onderdeel::where('afkorting', 'BB')->first();

    // Mark BB as admin-managed at the onderdeel level
    $bb->update(['beheerd_in_admin' => true]);

    $relatie->onderdelen()->attach($ha->id, ['van' => now()->subYear()->toDateString()]);
    $relatie->onderdelen()->attach($bb->id, ['van' => now()->subYear()->toDateString()]);

    // Sync with only HA — BB should NOT be closed because the onderdeel is admin-managed
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'jan@test.nl',
        'onderdeel_codes' => ['HA'],
    ], syncHeaders());

    $response->assertStatus(200);

    $relatie->refresh();

    // BB should still be open (onderdeel is admin-managed)
    $bbAssignment = $relatie->onderdelen()->where('onderdeel_id', $bb->id)->first();
    expect($bbAssignment->pivot->tot)->toBeNull();

    // HA should still be active too
    $haAssignment = $relatie->onderdelen()->where('onderdeel_id', $ha->id)->wherePivotNull('tot')->first();
    expect($haAssignment)->not->toBeNull();
});

test('deactivation closes assignments to admin-managed onderdelen too', function () {
    $user = User::factory()->create();
    $user->assignRole('member');
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'user_id' => $user->id,
        'actief' => true,
    ]);

    $ha = Onderdeel::where('afkorting', 'HA')->first();
    $bb = Onderdeel::where('afkorting', 'BB')->first();

    // Mark BB as admin-managed at the onderdeel level
    $bb->update(['beheerd_in_admin' => true]);

    $relatie->onderdelen()->attach($ha->id, ['van' => now()->subYear()->toDateString()]);
    $relatie->onderdelen()->attach($bb->id, ['van' => now()->subYear()->toDateString()]);

    $response = $this->deleteJson('/api/v1/sync/members/1000', [], syncHeaders());

    $response->assertStatus(200);

    $relatie->refresh();
    expect($relatie->actief)->toBeFalse();

    // Regular assignment should be closed
    $haAssignment = $relatie->onderdelen()->where('onderdeel_id', $ha->id)->first();
    expect($haAssignment->pivot->tot)->not->toBeNull();

    // Admin-managed onderdeel assignment should also be closed
    $bbAssignment = $relatie->onderdelen()->where('onderdeel_id', $bb->id)->first();
    expect($bbAssignment->pivot->tot)->not->toBeNull();
});

test('upsert skips admin-managed relatie entirely', function () {
    $user = User::factory()->create(['email' => 'admin-set@test.nl']);
    $user->assignRole('member');
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'voornaam' => 'Original',
        'achternaam' => 'Name',
        'user_id' => $user->id,
        'beheerd_in_admin' => true,
    ]);
    $relatie->emails()->create(['email' => 'admin-set@test.nl']);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Changed',
        'achternaam' => 'Different',
        'email' => 'sad@test.nl',
    ], syncHeaders());

    $response->assertStatus(200);
    $response->assertJson(['status' => 'skipped']);

    // Nothing should have changed
    $relatie->refresh();
    expect($relatie->voornaam)->toBe('Original');
    expect($relatie->achternaam)->toBe('Name');

    $user->refresh();
    expect($user->email)->toBe('admin-set@test.nl');

    // SAD email should not have been added
    expect($relatie->emails()->where('email', 'sad@test.nl')->exists())->toBeFalse();
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

test('does not add lid type when relatie already has a different active type', function () {
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'user_id' => null,
    ]);
    $relatie->emails()->create(['email' => 'dirigent@test.nl']);

    $dirigentType = RelatieType::where('naam', 'dirigent')->first();
    $relatie->types()->attach($dirigentType->id, ['van' => now()->subYear()->toDateString()]);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'dirigent@test.nl',
    ], syncHeaders());

    $response->assertStatus(200);

    $lidType = RelatieType::where('naam', 'lid')->first();
    expect($relatie->types()->where('relatie_type_id', $lidType->id)->exists())->toBeFalse();
    expect($relatie->types()->where('relatie_type_id', $dirigentType->id)->exists())->toBeTrue();
});

test('adds lid type when relatie has no active types', function () {
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

    $lidType = RelatieType::where('naam', 'lid')->first();
    expect($relatie->types()->where('relatie_type_id', $lidType->id)->exists())->toBeTrue();
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

test('deactivate skips admin-managed relatie', function () {
    $user = User::factory()->create();
    $user->assignRole('member');
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'user_id' => $user->id,
        'actief' => true,
        'beheerd_in_admin' => true,
    ]);

    $response = $this->deleteJson('/api/v1/sync/members/1000', [], syncHeaders());

    $response->assertStatus(200);
    $response->assertJson(['status' => 'skipped']);

    $relatie->refresh();
    expect($relatie->actief)->toBeTrue();
    expect($relatie->user_id)->toBe($user->id);
    expect(User::find($user->id))->not->toBeNull();
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

test('reconcile skips admin-managed relaties', function () {
    // Admin-managed relatie NOT in the active list — should NOT be deactivated
    $adminManaged = Relatie::factory()->create([
        'relatie_nummer' => 7777,
        'actief' => true,
        'beheerd_in_admin' => true,
    ]);

    // Regular relatie that IS in the active list
    Relatie::factory()->create(['relatie_nummer' => 1000, 'actief' => true]);

    $response = $this->postJson('/api/v1/sync/reconcile', [
        'active_lid_ids' => [1000],
    ], syncHeaders());

    $response->assertStatus(200);
    $response->assertJson(['deactivated_count' => 0]);

    $adminManaged->refresh();
    expect($adminManaged->actief)->toBeTrue();
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

// --- PII: Geboortedatum ---

test('creates relatie with geboortedatum', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'geboortedatum' => '15-03-1990',
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    expect($relatie->geboortedatum->format('Y-m-d'))->toBe('1990-03-15');
});

test('updates geboortedatum on existing relatie', function () {
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'geboortedatum' => '1985-01-01',
    ]);
    $relatie->emails()->create(['email' => 'jan@test.nl']);
    $user = User::factory()->create(['email' => 'jan@test.nl']);
    $user->assignRole('member');
    $relatie->update(['user_id' => $user->id]);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'jan@test.nl',
        'geboortedatum' => '15-03-1990',
    ], syncHeaders());

    $response->assertStatus(200);

    $relatie->refresh();
    expect($relatie->geboortedatum->format('Y-m-d'))->toBe('1990-03-15');
});

// --- PII: Adres ---

test('creates relatie with adres', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'adres' => 'Dorpsstraat 10a',
        'postcode' => '1985 AB',
        'plaats' => 'Driehuis',
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    $adres = $relatie->adressen()->first();
    expect($adres)->not->toBeNull();
    expect($adres->straat)->toBe('Dorpsstraat');
    expect($adres->huisnummer)->toBe('10');
    expect($adres->huisnummer_toevoeging)->toBe('a');
    expect($adres->postcode)->toBe('1985 AB');
    expect($adres->plaats)->toBe('Driehuis');
});

test('updates existing adres on update', function () {
    $relatie = Relatie::factory()->create(['relatie_nummer' => 1000]);
    $relatie->emails()->create(['email' => 'jan@test.nl']);
    $user = User::factory()->create(['email' => 'jan@test.nl']);
    $user->assignRole('member');
    $relatie->update(['user_id' => $user->id]);
    $relatie->adressen()->create([
        'straat' => 'Oude Straat',
        'huisnummer' => '5',
        'postcode' => '1000 AA',
        'plaats' => 'Amsterdam',
    ]);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'jan@test.nl',
        'adres' => 'Dorpsstraat 10',
        'postcode' => '1985 AB',
        'plaats' => 'Driehuis',
    ], syncHeaders());

    $response->assertStatus(200);

    $relatie->refresh();
    expect($relatie->adressen)->toHaveCount(1);

    $adres = $relatie->adressen()->first();
    expect($adres->straat)->toBe('Dorpsstraat');
    expect($adres->huisnummer)->toBe('10');
    expect($adres->postcode)->toBe('1985 AB');
    expect($adres->plaats)->toBe('Driehuis');
});

// --- PII: Telefoon ---

test('creates relatie with telefoon numbers', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'telefoon' => '06-12345678',
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    expect($relatie->telefoons)->toHaveCount(1);
    expect($relatie->telefoons->first()->nummer)->toBe('06-12345678');
});

test('replaces telefoon numbers on update', function () {
    $relatie = Relatie::factory()->create(['relatie_nummer' => 1000]);
    $relatie->emails()->create(['email' => 'jan@test.nl']);
    $user = User::factory()->create(['email' => 'jan@test.nl']);
    $user->assignRole('member');
    $relatie->update(['user_id' => $user->id]);
    $relatie->telefoons()->create(['nummer' => '06-old-number']);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => $relatie->voornaam,
        'achternaam' => $relatie->achternaam,
        'email' => 'jan@test.nl',
        'telefoon' => '06-new-number',
    ], syncHeaders());

    $response->assertStatus(200);

    $relatie->refresh();
    expect($relatie->telefoons)->toHaveCount(1);
    expect($relatie->telefoons->first()->nummer)->toBe('06-new-number');
});

test('splits multiple telefoon numbers', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'telefoon' => '0255-534403 06-11052119',
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    expect($relatie->telefoons)->toHaveCount(2);
    expect($relatie->telefoons->pluck('nummer')->sort()->values()->toArray())
        ->toBe(['0255-534403', '06-11052119']);
});

// --- PII: Instrument ---

test('syncs instrument to active onderdeel', function () {
    $this->seed(InstrumentSoortSeeder::class);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'onderdeel_codes' => ['HA'],
        'instrument' => 'Trompet',
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    $ha = Onderdeel::where('afkorting', 'HA')->first();
    $trompet = InstrumentSoort::where('naam', 'Trompet')->first();

    $ri = RelatieInstrument::where('relatie_id', $relatie->id)
        ->where('onderdeel_id', $ha->id)
        ->where('instrument_soort_id', $trompet->id)
        ->first();

    expect($ri)->not->toBeNull();
});

test('warns when instrument name not found', function () {
    $this->seed(InstrumentSoortSeeder::class);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'onderdeel_codes' => ['HA'],
        'instrument' => 'Banjo',
    ], syncHeaders());

    $response->assertStatus(201);
    $response->assertJsonFragment(['Unknown instrument: Banjo']);
});

// --- PII: Skip behavior ---

test('skips pii fields when null', function () {
    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Jan',
        'achternaam' => 'Jansen',
        'email' => 'jan@test.nl',
        'geboortedatum' => null,
        'adres' => null,
        'telefoon' => null,
        'instrument' => null,
    ], syncHeaders());

    $response->assertStatus(201);

    $relatie = Relatie::where('relatie_nummer', 1000)->first();
    expect($relatie->geboortedatum)->toBeNull();
    expect($relatie->adressen)->toHaveCount(0);
    expect($relatie->telefoons)->toHaveCount(0);
});

test('skips pii for admin-managed members', function () {
    $user = User::factory()->create(['email' => 'admin-set@test.nl']);
    $user->assignRole('member');
    $relatie = Relatie::factory()->create([
        'relatie_nummer' => 1000,
        'user_id' => $user->id,
        'beheerd_in_admin' => true,
        'geboortedatum' => '1985-01-01',
    ]);
    $relatie->emails()->create(['email' => 'admin-set@test.nl']);

    $response = $this->putJson('/api/v1/sync/members/1000', [
        'voornaam' => 'Changed',
        'achternaam' => 'Different',
        'email' => 'sad@test.nl',
        'geboortedatum' => '15-03-1990',
        'adres' => 'Dorpsstraat 10',
        'telefoon' => '06-12345678',
    ], syncHeaders());

    $response->assertStatus(200);
    $response->assertJson(['status' => 'skipped']);

    $relatie->refresh();
    expect($relatie->geboortedatum->format('Y-m-d'))->toBe('1985-01-01');
    expect($relatie->adressen)->toHaveCount(0);
    expect($relatie->telefoons)->toHaveCount(0);
});
