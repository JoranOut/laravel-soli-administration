<?php

use App\Models\ClientRoleMapping;
use App\Models\OauthClientSetting;
use App\Models\OauthClientUserRole;
use App\Models\RelatieType;
use App\Models\User;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Passport\Client;

function createOauthClient(): Client
{
    return Client::create([
        'name' => 'Test Client',
        'secret' => 'secret',
        'redirect_uris' => ['http://localhost/callback'],
        'grant_types' => ['authorization_code'],
        'revoked' => false,
    ]);
}

test('guests are redirected to the login page', function () {
    $response = $this->get(route('admin.oauth-clients.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin gets 403 on oauth clients page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get(route('admin.oauth-clients.index'));
    $response->assertForbidden();
});

test('admin can view the oauth clients page', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create()->assignRole('admin');
    createOauthClient();

    $response = $this->actingAs($admin)->get(route('admin.oauth-clients.index'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/oauth-clients/index')
        ->has('clients', 1)
        ->has('relatieTypes')
        ->has('users')
    );
});

test('admin can save client settings with role mappings', function () {
    $this->seed([RolesAndPermissionsSeeder::class, RelatieTypeSeeder::class]);

    $admin = User::factory()->create()->assignRole('admin');
    $client = createOauthClient();
    $lidType = RelatieType::where('naam', 'lid')->first();
    $bestuurType = RelatieType::where('naam', 'bestuur')->first();

    $response = $this->actingAs($admin)->put(route('admin.oauth-clients.update', $client->id), [
        'type' => 'wordpress',
        'default_role' => 'subscriber',
        'role_mappings' => [
            ['relatie_type_id' => $bestuurType->id, 'mapped_role' => 'editor'],
            ['relatie_type_id' => $lidType->id, 'mapped_role' => 'subscriber'],
        ],
        'user_roles' => [],
    ]);

    $response->assertRedirect();

    $setting = OauthClientSetting::where('client_id', $client->id)->first();
    expect($setting)->not->toBeNull();
    expect($setting->type)->toBe('wordpress');
    expect($setting->default_role)->toBe('subscriber');
    expect($setting->roleMappings)->toHaveCount(2);

    // Priority is derived from array order
    $mappings = $setting->roleMappings()->orderBy('priority')->get();
    expect($mappings[0]->relatie_type_id)->toBe($bestuurType->id);
    expect($mappings[0]->mapped_role)->toBe('editor');
    expect($mappings[0]->priority)->toBe(0);
    expect($mappings[1]->relatie_type_id)->toBe($lidType->id);
    expect($mappings[1]->priority)->toBe(1);
});

test('admin can update existing client settings', function () {
    $this->seed([RolesAndPermissionsSeeder::class, RelatieTypeSeeder::class]);

    $admin = User::factory()->create()->assignRole('admin');
    $client = createOauthClient();
    $lidType = RelatieType::where('naam', 'lid')->first();

    // Create initial settings
    $setting = OauthClientSetting::create([
        'client_id' => $client->id,
        'type' => 'wordpress',
        'default_role' => 'subscriber',
    ]);
    ClientRoleMapping::create([
        'client_setting_id' => $setting->id,
        'relatie_type_id' => $lidType->id,
        'mapped_role' => 'subscriber',
        'priority' => 0,
    ]);

    // Update to new settings (empty mappings)
    $response = $this->actingAs($admin)->put(route('admin.oauth-clients.update', $client->id), [
        'type' => 'other',
        'default_role' => 'viewer',
        'role_mappings' => [],
        'user_roles' => [],
    ]);

    $response->assertRedirect();

    $setting->refresh();
    expect($setting->type)->toBe('other');
    expect($setting->default_role)->toBe('viewer');
    expect($setting->roleMappings)->toHaveCount(0);
});

test('non-admin cannot update client settings', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $member = User::factory()->create()->assignRole('member');
    $client = createOauthClient();

    $response = $this->actingAs($member)->put(route('admin.oauth-clients.update', $client->id), [
        'type' => 'wordpress',
        'default_role' => 'subscriber',
        'role_mappings' => [],
        'user_roles' => [],
    ]);

    $response->assertForbidden();
});

test('admin can save client settings with user role overrides', function () {
    $this->seed([RolesAndPermissionsSeeder::class, RelatieTypeSeeder::class]);

    $admin = User::factory()->create()->assignRole('admin');
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $client = createOauthClient();

    // First save: two user overrides
    $response = $this->actingAs($admin)->put(route('admin.oauth-clients.update', $client->id), [
        'type' => 'wordpress',
        'default_role' => 'subscriber',
        'role_mappings' => [],
        'user_roles' => [
            ['user_id' => $user1->id, 'mapped_role' => 'administrator'],
            ['user_id' => $user2->id, 'mapped_role' => 'editor'],
        ],
    ]);

    $response->assertRedirect();

    $setting = OauthClientSetting::where('client_id', $client->id)->first();
    expect($setting->userRoles)->toHaveCount(2);

    $roles = $setting->userRoles()->orderBy('user_id')->get();
    expect($roles[0]->user_id)->toBe($user1->id);
    expect($roles[0]->mapped_role)->toBe('administrator');
    expect($roles[1]->user_id)->toBe($user2->id);
    expect($roles[1]->mapped_role)->toBe('editor');

    // Re-save replaces the overrides
    $this->actingAs($admin)->put(route('admin.oauth-clients.update', $client->id), [
        'type' => 'wordpress',
        'default_role' => 'subscriber',
        'role_mappings' => [],
        'user_roles' => [
            ['user_id' => $user1->id, 'mapped_role' => 'subscriber'],
        ],
    ]);

    $setting->refresh();
    expect($setting->userRoles)->toHaveCount(1);
    expect($setting->userRoles->first()->user_id)->toBe($user1->id);
    expect($setting->userRoles->first()->mapped_role)->toBe('subscriber');

    // index() returns overrides
    $response = $this->actingAs($admin)->get(route('admin.oauth-clients.index'));
    $response->assertInertia(fn ($page) => $page
        ->component('admin/oauth-clients/index')
        ->has('clients.0.setting.user_roles', 1)
        ->where('clients.0.setting.user_roles.0.user_id', $user1->id)
        ->where('clients.0.setting.user_roles.0.mapped_role', 'subscriber')
        ->where('clients.0.setting.user_roles.0.user_name', $user1->name)
    );
});

test('user_roles must be present in update payload', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create()->assignRole('admin');
    $client = createOauthClient();

    $response = $this->actingAs($admin)->put(route('admin.oauth-clients.update', $client->id), [
        'type' => 'wordpress',
        'default_role' => 'subscriber',
        'role_mappings' => [],
    ]);

    $response->assertSessionHasErrors('user_roles');
});
