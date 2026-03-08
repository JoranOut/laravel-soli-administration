<?php

use App\Models\Relatie;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin.activity-log.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin gets 403 on activity log page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get(route('admin.activity-log.index'));
    $response->assertForbidden();
});

test('admin can view the activity log page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.activity-log.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/activity-log'));
});

test('updating a relatie creates an activity log entry', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan', 'achternaam' => 'Jansen']);

    // Clear any activity from factory creation
    \Spatie\Activitylog\Models\Activity::query()->delete();

    $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}", [
        'voornaam' => 'Piet',
        'achternaam' => 'Jansen',
        'geslacht' => $relatie->geslacht,
        'actief' => $relatie->actief,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.activity-log.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/activity-log')
        ->has('activities.data', 1)
        ->where('activities.data.0.event', 'updated')
        ->where('activities.data.0.causer.name', $admin->name)
    );
});
