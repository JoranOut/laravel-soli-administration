<?php

use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\User;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('member with linked relatie sees relatie data on dashboard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RelatieTypeSeeder::class);

    $member = User::factory()->create()->assignRole('member');
    $relatie = Relatie::factory()->create(['user_id' => $member->id]);

    $response = $this->actingAs($member)->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/show')
        ->has('relatie')
        ->where('relatie.id', $relatie->id)
    );
});

test('member without linked relatie sees not-linked page on dashboard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $member = User::factory()->create()->assignRole('member');

    $response = $this->actingAs($member)->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/relaties/not-linked')
    );
});

test('admin dashboard includes onderdeel history chart data', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create()->assignRole('admin');

    $orkest = Onderdeel::factory()->create(['type' => 'muziekgroep', 'naam' => 'Harmonie']);
    $relatie = Relatie::factory()->create();

    DB::table('soli_relatie_onderdeel')->insert([
        'relatie_id' => $relatie->id,
        'onderdeel_id' => $orkest->id,
        'van' => now()->subMonths(3)->toDateString(),
        'tot' => null,
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->has('onderdeel_history')
        ->has('onderdeel_names')
        ->where('onderdeel_names', ['Harmonie'])
    );
});

test('onderdeel history counts active members correctly per month', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create()->assignRole('admin');

    $orkest = Onderdeel::factory()->create(['type' => 'muziekgroep', 'naam' => 'Harmonie']);
    $relatie1 = Relatie::factory()->create();
    $relatie2 = Relatie::factory()->create();

    // relatie1: active from 2 months ago, still active
    DB::table('soli_relatie_onderdeel')->insert([
        'relatie_id' => $relatie1->id,
        'onderdeel_id' => $orkest->id,
        'van' => now()->subMonths(2)->startOfMonth()->toDateString(),
        'tot' => null,
    ]);

    // relatie2: active from 2 months ago, ended last month
    DB::table('soli_relatie_onderdeel')->insert([
        'relatie_id' => $relatie2->id,
        'onderdeel_id' => $orkest->id,
        'van' => now()->subMonths(2)->startOfMonth()->toDateString(),
        'tot' => now()->subMonth()->endOfMonth()->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));
    $response->assertOk();

    $history = $response->original->getData()['page']['props']['onderdeel_history'];
    $currentMonth = now()->format('Y-m');
    $lastMonth = now()->subMonth()->format('Y-m');

    $currentRow = collect($history)->firstWhere('month', $currentMonth);
    $lastMonthRow = collect($history)->firstWhere('month', $lastMonth);

    // Current month: only relatie1 is active
    expect($currentRow['Harmonie'])->toBe(1);
    // Last month: both were active
    expect($lastMonthRow['Harmonie'])->toBe(2);
});

test('onderdeel history includes old data for all-years toggle', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create()->assignRole('admin');

    $orkest = Onderdeel::factory()->create(['type' => 'muziekgroep', 'naam' => 'Harmonie']);
    $relatie = Relatie::factory()->create();

    // Record that ended 6 years ago — should still be included in full history
    DB::table('soli_relatie_onderdeel')->insert([
        'relatie_id' => $relatie->id,
        'onderdeel_id' => $orkest->id,
        'van' => now()->subYears(7)->toDateString(),
        'tot' => now()->subYears(6)->toDateString(),
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));
    $response->assertOk();

    $history = $response->original->getData()['page']['props']['onderdeel_history'];

    // History should go back to the record's start date
    $oldMonth = now()->subYears(7)->format('Y-m');
    $oldRow = collect($history)->firstWhere('month', $oldMonth);
    expect($oldRow)->not->toBeNull();
    expect($oldRow['Harmonie'])->toBe(1);
});
