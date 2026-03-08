<?php

use App\Models\Email;
use App\Models\Relatie;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

test('admin can delete a linked user account', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $linkedUser = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);

    $this->actingAs($admin)
        ->delete("/admin/relaties/{$relatie->id}/account")
        ->assertRedirect();

    $relatie->refresh();
    expect($relatie->user_id)->toBeNull();
    expect(User::find($linkedUser->id))->toBeNull();
});

test('non-admin gets 403 when deleting a linked user account', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');

    $linkedUser = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);

    $this->actingAs($bestuur)
        ->delete("/admin/relaties/{$relatie->id}/account")
        ->assertForbidden();

    expect(User::find($linkedUser->id))->not->toBeNull();
});

test('guest gets redirected when deleting a linked user account', function () {
    $linkedUser = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);

    $this->delete("/admin/relaties/{$relatie->id}/account")
        ->assertRedirect('/login');
});

test('deleting account when no user is linked redirects with error', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create(['user_id' => null]);

    $this->actingAs($admin)
        ->delete("/admin/relaties/{$relatie->id}/account")
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('setting relatie inactive auto-deletes linked user account', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $linkedUser = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id, 'actief' => true]);

    $this->actingAs($admin)
        ->put("/admin/relaties/{$relatie->id}", [
            'voornaam' => $relatie->voornaam,
            'tussenvoegsel' => $relatie->tussenvoegsel,
            'achternaam' => $relatie->achternaam,
            'geslacht' => $relatie->geslacht,
            'geboortedatum' => $relatie->geboortedatum?->format('Y-m-d'),
            'actief' => false,
            'geboorteplaats' => $relatie->geboorteplaats,
            'nationaliteit' => $relatie->nationaliteit,
        ])
        ->assertRedirect();

    $relatie->refresh();
    expect($relatie->actief)->toBeFalse();
    expect($relatie->user_id)->toBeNull();
    expect(User::find($linkedUser->id))->toBeNull();
});

test('setting relatie inactive without linked user does not error', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create(['user_id' => null, 'actief' => true]);

    $this->actingAs($admin)
        ->put("/admin/relaties/{$relatie->id}", [
            'voornaam' => $relatie->voornaam,
            'tussenvoegsel' => $relatie->tussenvoegsel,
            'achternaam' => $relatie->achternaam,
            'geslacht' => $relatie->geslacht,
            'geboortedatum' => $relatie->geboortedatum?->format('Y-m-d'),
            'actief' => false,
            'geboorteplaats' => $relatie->geboorteplaats,
            'nationaliteit' => $relatie->nationaliteit,
        ])
        ->assertRedirect();

    $relatie->refresh();
    expect($relatie->actief)->toBeFalse();
});

test('keeping relatie active does not delete linked user', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $linkedUser = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id, 'actief' => true]);

    $this->actingAs($admin)
        ->put("/admin/relaties/{$relatie->id}", [
            'voornaam' => 'Updated',
            'tussenvoegsel' => $relatie->tussenvoegsel,
            'achternaam' => $relatie->achternaam,
            'geslacht' => $relatie->geslacht,
            'geboortedatum' => $relatie->geboortedatum?->format('Y-m-d'),
            'actief' => true,
            'geboorteplaats' => $relatie->geboorteplaats,
            'nationaliteit' => $relatie->nationaliteit,
        ])
        ->assertRedirect();

    $relatie->refresh();
    expect($relatie->user_id)->toBe($linkedUser->id);
    expect(User::find($linkedUser->id))->not->toBeNull();
});

test('admin can update login email to a relatie email address', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $linkedUser = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()])->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);
    $relatie->emails()->create(['email' => 'old@example.com']);
    $relatie->emails()->create(['email' => 'new@example.com']);

    $this->actingAs($admin)
        ->put("/admin/relaties/{$relatie->id}/account", ['email' => 'new@example.com'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $linkedUser->refresh();
    expect($linkedUser->email)->toBe('new@example.com');
});

test('updating login email clears email_verified_at', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $linkedUser = User::factory()->create(['email' => 'old@example.com', 'email_verified_at' => now()])->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);
    $relatie->emails()->create(['email' => 'old@example.com']);
    $relatie->emails()->create(['email' => 'new@example.com']);

    $this->actingAs($admin)
        ->put("/admin/relaties/{$relatie->id}/account", ['email' => 'new@example.com'])
        ->assertRedirect();

    $linkedUser->refresh();
    expect($linkedUser->email_verified_at)->toBeNull();
});

test('cannot update login email to one already used by another user', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $otherUser = User::factory()->create(['email' => 'taken@example.com']);
    $linkedUser = User::factory()->create(['email' => 'current@example.com'])->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);
    $relatie->emails()->create(['email' => 'current@example.com']);
    $relatie->emails()->create(['email' => 'taken@example.com']);

    $this->actingAs($admin)
        ->put("/admin/relaties/{$relatie->id}/account", ['email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');

    $linkedUser->refresh();
    expect($linkedUser->email)->toBe('current@example.com');
});

test('cannot update login email to one not in relatie emails', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $linkedUser = User::factory()->create(['email' => 'current@example.com'])->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);
    $relatie->emails()->create(['email' => 'current@example.com']);

    $this->actingAs($admin)
        ->put("/admin/relaties/{$relatie->id}/account", ['email' => 'nonexistent@example.com'])
        ->assertSessionHasErrors('email');

    $linkedUser->refresh();
    expect($linkedUser->email)->toBe('current@example.com');
});

test('non-admin gets 403 when updating login email', function () {
    $bestuur = User::factory()->create()->assignRole('bestuur');

    $linkedUser = User::factory()->create(['email' => 'current@example.com'])->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);
    $relatie->emails()->create(['email' => 'new@example.com']);

    $this->actingAs($bestuur)
        ->put("/admin/relaties/{$relatie->id}/account", ['email' => 'new@example.com'])
        ->assertForbidden();
});

test('guest gets redirected when updating login email', function () {
    $linkedUser = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $linkedUser->id]);

    $this->put("/admin/relaties/{$relatie->id}/account", ['email' => 'new@example.com'])
        ->assertRedirect('/login');
});

test('self-service profile delete route no longer exists', function () {
    $user = User::factory()->create()->assignRole('member');

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        ->assertStatus(405);
});
