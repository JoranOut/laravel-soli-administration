<?php

use App\Models\Relatie;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->withoutVite();
});

test('admin can view the koppelingen page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $unlinkedUser = User::factory()->create();
    $unlinkedRelatie = Relatie::factory()->create();

    $this->actingAs($admin)
        ->get('/admin/koppelingen')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/koppelingen')
            ->has('unlinkedUsers')
            ->has('unlinkedRelaties')
        );
});

test('admin can link a user to a relatie', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    $relatie = Relatie::factory()->create();

    $this->actingAs($admin)
        ->post('/admin/koppelingen', [
            'user_id' => $user->id,
            'relatie_id' => $relatie->id,
        ])
        ->assertRedirect();

    $relatie->refresh();
    expect($relatie->user_id)->toBe($user->id);
});

test('cannot link an already-linked user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    $existingRelatie = Relatie::factory()->create(['user_id' => $user->id]);
    $newRelatie = Relatie::factory()->create();

    $this->actingAs($admin)
        ->post('/admin/koppelingen', [
            'user_id' => $user->id,
            'relatie_id' => $newRelatie->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('user_id');

    $newRelatie->refresh();
    expect($newRelatie->user_id)->toBeNull();
});

test('cannot link an already-linked relatie', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $existingUser = User::factory()->create();
    $relatie = Relatie::factory()->create(['user_id' => $existingUser->id]);
    $newUser = User::factory()->create();

    $this->actingAs($admin)
        ->post('/admin/koppelingen', [
            'user_id' => $newUser->id,
            'relatie_id' => $relatie->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('relatie_id');

    $relatie->refresh();
    expect($relatie->user_id)->toBe($existingUser->id);
});

test('admin can unlink a relatie', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $user = User::factory()->create();
    $relatie = Relatie::factory()->create(['user_id' => $user->id]);

    $this->actingAs($admin)
        ->delete("/admin/koppelingen/{$relatie->id}")
        ->assertRedirect();

    $relatie->refresh();
    expect($relatie->user_id)->toBeNull();
});

test('non-admin gets 403 on koppelingen page', function () {
    $member = User::factory()->create();
    $member->assignRole('member');

    $this->actingAs($member)
        ->get('/admin/koppelingen')
        ->assertForbidden();
});

test('dashboard shows alerts for admin with unlinked records', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // Create unlinked user and relatie
    User::factory()->create();
    Relatie::factory()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('alerts')
            ->where('alerts.unlinked_users', fn ($value) => $value > 0)
            ->where('alerts.unlinked_relaties', fn ($value) => $value > 0)
        );
});

test('dashboard does not show alerts for bestuur', function () {
    $bestuur = User::factory()->create();
    $bestuur->assignRole('bestuur');

    $this->actingAs($bestuur)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->missing('alerts')
        );
});
