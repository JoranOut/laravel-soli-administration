<?php

use App\Jobs\SyncGoogleContactsJob;
use App\Models\Email;
use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\User;
use App\Observers\GoogleContactSyncObserver;
use Database\Seeders\OnderdeelSeeder;
use Database\Seeders\RelatieTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RelatieTypeSeeder::class);
    Bus::fake();
});

afterEach(function () {
    GoogleContactSyncObserver::$disabled = false;
});

// --- Relatie Observer ---

test('updating relatie name dispatches sync job', function () {
    $relatie = Relatie::factory()->create(['voornaam' => 'Jan']);

    $relatie->update(['voornaam' => 'Piet']);

    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === $relatie->id);
});

test('updating relatie actief dispatches sync job', function () {
    $relatie = Relatie::factory()->create(['actief' => true]);

    $relatie->update(['actief' => false]);

    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === $relatie->id);
});

test('updating unrelated relatie field does not dispatch sync job', function () {
    $relatie = Relatie::factory()->create();

    $relatie->update(['geboortedatum' => '1990-01-01']);

    Bus::assertNotDispatched(SyncGoogleContactsJob::class);
});

// --- Email Observer ---

test('creating email dispatches sync job', function () {
    $relatie = Relatie::factory()->create();

    $relatie->emails()->create(['email' => 'test@example.com']);

    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === $relatie->id);
});

test('deleting email dispatches sync job', function () {
    $relatie = Relatie::factory()->create();
    $email = $relatie->emails()->create(['email' => 'delete-me@example.com']);

    $email->delete();

    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === $relatie->id);
});

// --- Controller: Onderdeel management ---

test('attaching onderdeel dispatches sync job', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $onderdeel = Onderdeel::factory()->create();

    $this->actingAs($admin)->post("/admin/relaties/{$relatie->id}/onderdelen", [
        'onderdeel_id' => $onderdeel->id,
        'van' => '2026-01-01',
    ]);

    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === $relatie->id);
});

test('updating onderdeel dispatches sync job', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $onderdeel = Onderdeel::factory()->create();

    $relatie->onderdelen()->attach($onderdeel->id, ['van' => '2025-01-01']);
    $pivotId = DB::table('soli_relatie_onderdeel')
        ->where('relatie_id', $relatie->id)
        ->where('onderdeel_id', $onderdeel->id)
        ->value('id');

    $this->actingAs($admin)->put("/admin/relaties/{$relatie->id}/onderdelen/{$pivotId}", [
        'van' => '2025-01-01',
        'tot' => '2026-12-31',
    ]);

    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === $relatie->id);
});

test('detaching onderdeel dispatches sync job', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $relatie = Relatie::factory()->create();
    $onderdeel = Onderdeel::factory()->create();

    $relatie->onderdelen()->attach($onderdeel->id, ['van' => '2025-01-01']);
    $pivotId = DB::table('soli_relatie_onderdeel')
        ->where('relatie_id', $relatie->id)
        ->where('onderdeel_id', $onderdeel->id)
        ->value('id');

    $this->actingAs($admin)->delete("/admin/relaties/{$relatie->id}/onderdelen/{$pivotId}");

    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === $relatie->id);
});

// --- Controller: Relatie creation ---

test('creating relatie dispatches sync job', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)->post('/admin/relaties', [
        'relatie_nummer' => 8888,
        'voornaam' => 'Sync',
        'achternaam' => 'Test',
        'geslacht' => 'M',
        'emails' => [
            ['email' => 'sync-test@example.com'],
        ],
    ]);

    $relatie = Relatie::where('relatie_nummer', 8888)->first();

    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === $relatie->id);
});

// --- Observer disabled flag ---

test('disabled observer flag suppresses dispatch', function () {
    GoogleContactSyncObserver::$disabled = true;

    $relatie = Relatie::factory()->create(['voornaam' => 'Jan']);
    $relatie->update(['voornaam' => 'Piet']);

    $relatie->emails()->create(['email' => 'suppressed@example.com']);

    Bus::assertNotDispatched(SyncGoogleContactsJob::class);
});

// --- Import flow ---

test('import suppresses observers and dispatches full sync', function () {
    $this->seed(OnderdeelSeeder::class);

    $fixturePath = base_path('tests/fixtures/sad-members-sample.json');

    $this->artisan('import:sad-members', ['path' => $fixturePath])
        ->assertSuccessful();

    // The import disables observers and dispatches a single full sync at the end (no relatieId)
    Bus::assertDispatched(SyncGoogleContactsJob::class, fn ($job) => $job->relatieId === null);

    // Observer was re-enabled after import
    expect(GoogleContactSyncObserver::$disabled)->toBeFalse();
});

test('import dry-run does not dispatch sync job', function () {
    $this->seed(OnderdeelSeeder::class);

    $fixturePath = base_path('tests/fixtures/sad-members-sample.json');

    $this->artisan('import:sad-members', ['path' => $fixturePath, '--dry-run' => true])
        ->assertSuccessful();

    Bus::assertNotDispatched(SyncGoogleContactsJob::class);
});
