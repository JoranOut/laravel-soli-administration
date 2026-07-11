<?php

use App\Jobs\SyncGoogleContactsJob;
use App\Models\GoogleContactSyncLog;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Queue;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin.google-contacts-sync.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin gets 403 on google contacts sync page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get(route('admin.google-contacts-sync.index'));
    $response->assertForbidden();
});

test('admin can view the google contacts sync page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->get(route('admin.google-contacts-sync.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('admin/google-contacts-sync'));
});

test('admin can trigger full sync', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    Queue::fake();

    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->post(route('admin.google-contacts-sync.store'));
    $response->assertRedirect();

    Queue::assertPushed(SyncGoogleContactsJob::class);
});

test('sync logs are displayed on the page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create()->assignRole('admin');

    GoogleContactSyncLog::create([
        'type' => 'full',
        'status' => 'completed',
        'workspace_users' => 3,
        'contacts_created' => 15,
        'contacts_updated' => 5,
        'contacts_deleted' => 2,
        'contacts_skipped' => 100,
        'started_at' => now()->subMinutes(5),
        'completed_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('admin.google-contacts-sync.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/google-contacts-sync')
        ->has('logs.data', 1)
        ->where('logs.data.0.status', 'completed')
        ->where('logs.data.0.contacts_created', 15)
    );
});
