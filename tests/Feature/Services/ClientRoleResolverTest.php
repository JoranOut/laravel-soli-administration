<?php

use App\Models\ClientRoleMapping;
use App\Models\OauthClientSetting;
use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\User;
use App\Services\ClientRoleResolver;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Passport\Client;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, RelatieTypeSeeder::class]);
    $this->resolver = new ClientRoleResolver();
});

function createClient(): Client
{
    return Client::create([
        'name' => 'Test Client',
        'secret' => 'secret',
        'redirect_uris' => ['http://localhost/callback'],
        'grant_types' => ['authorization_code'],
        'revoked' => false,
    ]);
}

function createSettingWithMappings(string $clientId, array $mappings, string $defaultRole = 'subscriber'): OauthClientSetting
{
    $setting = OauthClientSetting::create([
        'client_id' => $clientId,
        'type' => 'wordpress',
        'default_role' => $defaultRole,
    ]);

    foreach ($mappings as $priority => $mapping) {
        $type = RelatieType::where('naam', $mapping['type'])->first();
        ClientRoleMapping::create([
            'client_setting_id' => $setting->id,
            'relatie_type_id' => $type->id,
            'mapped_role' => $mapping['role'],
            'priority' => $priority,
        ]);
    }

    return $setting;
}

function createUserWithRelatieTypes(array $typeNames): User
{
    $user = User::factory()->create();
    $user->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $user->id]);

    foreach ($typeNames as $typeName) {
        $type = RelatieType::where('naam', $typeName)->first();
        $relatie->types()->attach($type->id, [
            'van' => now()->subYear(),
            'tot' => null,
        ]);
    }

    return $user;
}

test('returns spatie roles when no client settings exist', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $client = createClient();

    $roles = $this->resolver->resolve($user, $client->id);

    expect($roles)->toBe(['admin']);
});

test('returns mapped role for a single matching relatie type', function () {
    $client = createClient();
    createSettingWithMappings($client->id, [
        ['type' => 'lid', 'role' => 'subscriber'],
    ]);

    $user = createUserWithRelatieTypes(['lid']);

    $roles = $this->resolver->resolve($user, $client->id);

    expect($roles)->toBe(['subscriber']);
});

test('returns highest priority role when multiple relatie types match', function () {
    $client = createClient();
    createSettingWithMappings($client->id, [
        ['type' => 'bestuur', 'role' => 'editor'],       // priority 0 (highest)
        ['type' => 'lid', 'role' => 'subscriber'],        // priority 1
    ]);

    $user = createUserWithRelatieTypes(['lid', 'bestuur']);

    $roles = $this->resolver->resolve($user, $client->id);

    expect($roles)->toBe(['editor']);
});

test('returns default role when no relatie types match', function () {
    $client = createClient();
    createSettingWithMappings($client->id, [
        ['type' => 'bestuur', 'role' => 'editor'],
    ], 'subscriber');

    $user = createUserWithRelatieTypes(['lid']);

    $roles = $this->resolver->resolve($user, $client->id);

    expect($roles)->toBe(['subscriber']);
});

test('returns empty array when no match and no default role', function () {
    $client = createClient();

    $setting = OauthClientSetting::create([
        'client_id' => $client->id,
        'type' => 'wordpress',
        'default_role' => null,
    ]);

    ClientRoleMapping::create([
        'client_setting_id' => $setting->id,
        'relatie_type_id' => RelatieType::where('naam', 'bestuur')->first()->id,
        'mapped_role' => 'editor',
        'priority' => 0,
    ]);

    $user = createUserWithRelatieTypes(['lid']);

    $roles = $this->resolver->resolve($user, $client->id);

    expect($roles)->toBe([]);
});

test('ignores expired relatie types', function () {
    $client = createClient();
    createSettingWithMappings($client->id, [
        ['type' => 'bestuur', 'role' => 'editor'],
        ['type' => 'lid', 'role' => 'subscriber'],
    ]);

    $user = User::factory()->create();
    $user->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $user->id]);

    // Active lid type
    $lidType = RelatieType::where('naam', 'lid')->first();
    $relatie->types()->attach($lidType->id, [
        'van' => now()->subYear(),
        'tot' => null,
    ]);

    // Expired bestuur type
    $bestuurType = RelatieType::where('naam', 'bestuur')->first();
    $relatie->types()->attach($bestuurType->id, [
        'van' => now()->subYears(3),
        'tot' => now()->subYear(),
    ]);

    $roles = $this->resolver->resolve($user, $client->id);

    // bestuur is expired, so only lid matches → subscriber
    expect($roles)->toBe(['subscriber']);
});

test('merges relatie types across multiple relaties', function () {
    $client = createClient();
    createSettingWithMappings($client->id, [
        ['type' => 'bestuur', 'role' => 'editor'],       // priority 0
        ['type' => 'lid', 'role' => 'subscriber'],        // priority 1
    ]);

    $user = User::factory()->create();
    $user->assignRole('member');

    // First relatie with lid type
    $relatie1 = Relatie::factory()->create(['user_id' => $user->id]);
    $lidType = RelatieType::where('naam', 'lid')->first();
    $relatie1->types()->attach($lidType->id, [
        'van' => now()->subYear(),
        'tot' => null,
    ]);

    // Second relatie with bestuur type
    $relatie2 = Relatie::factory()->create(['user_id' => $user->id]);
    $bestuurType = RelatieType::where('naam', 'bestuur')->first();
    $relatie2->types()->attach($bestuurType->id, [
        'van' => now()->subYear(),
        'tot' => null,
    ]);

    $roles = $this->resolver->resolve($user, $client->id);

    // bestuur has higher priority → editor
    expect($roles)->toBe(['editor']);
});

test('returns default role when user has no relaties', function () {
    $client = createClient();
    createSettingWithMappings($client->id, [
        ['type' => 'lid', 'role' => 'subscriber'],
    ], 'subscriber');

    $user = User::factory()->create();
    $user->assignRole('member');

    $roles = $this->resolver->resolve($user, $client->id);

    expect($roles)->toBe(['subscriber']);
});

test('no-access mapping returns empty roles', function () {
    $client = createClient();
    createSettingWithMappings($client->id, [
        ['type' => 'donateur', 'role' => ClientRoleResolver::NO_ACCESS],
    ]);

    $user = createUserWithRelatieTypes(['donateur']);

    $roles = $this->resolver->resolve($user, $client->id);

    expect($roles)->toBe([]);
});

test('no-access as default role returns empty roles', function () {
    $client = createClient();
    createSettingWithMappings($client->id, [
        ['type' => 'bestuur', 'role' => 'editor'],
    ], ClientRoleResolver::NO_ACCESS);

    $user = createUserWithRelatieTypes(['lid']);

    $roles = $this->resolver->resolve($user, $client->id);

    expect($roles)->toBe([]);
});

test('no-access wins over lower-priority match by priority', function () {
    $client = createClient();
    createSettingWithMappings($client->id, [
        ['type' => 'donateur', 'role' => ClientRoleResolver::NO_ACCESS],  // priority 0
        ['type' => 'lid', 'role' => 'subscriber'],                        // priority 1
    ]);

    // User is both donateur and lid — donateur has higher priority → no access
    $user = createUserWithRelatieTypes(['lid', 'donateur']);

    $roles = $this->resolver->resolve($user, $client->id);

    expect($roles)->toBe([]);
});
