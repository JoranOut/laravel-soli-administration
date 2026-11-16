<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function activityLogV5Migration(): object
{
    return require base_path('database/migrations/2026_11_16_100001_upgrade_activity_log_to_v5.php');
}

function insertV4Activity(?string $event, ?array $properties): int
{
    return DB::table('soli_activity_log')->insertGetId([
        'log_name' => 'default',
        'description' => 'test',
        'event' => $event,
        'properties' => $properties === null ? null : json_encode($properties),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function activityRow(int $id): object
{
    return DB::table('soli_activity_log')->find($id);
}

beforeEach(function () {
    activityLogV5Migration()->down();
});

afterEach(function () {
    // DDL commits the test transaction in MySQL, so the rows would outlive the test.
    DB::table('soli_activity_log')->delete();

    if (! Schema::hasColumn('soli_activity_log', 'attribute_changes')) {
        activityLogV5Migration()->up();
    }
});

test('model event changes move from properties to attribute_changes', function () {
    $updated = insertV4Activity('updated', [
        'attributes' => ['voornaam' => 'Piet'],
        'old' => ['voornaam' => 'Jan'],
    ]);
    $deleted = insertV4Activity('deleted', ['old' => ['naam' => 'Bigband'], 'extra' => 'blijft']);

    activityLogV5Migration()->up();

    expect(json_decode(activityRow($updated)->attribute_changes, true))->toEqual([
        'attributes' => ['voornaam' => 'Piet'],
        'old' => ['voornaam' => 'Jan'],
    ])
        ->and(activityRow($updated)->properties)->toBeNull()
        ->and(json_decode(activityRow($deleted)->attribute_changes, true))->toEqual(['old' => ['naam' => 'Bigband']])
        ->and(json_decode(activityRow($deleted)->properties, true))->toEqual(['extra' => 'blijft'])
        ->and(Schema::hasColumn('soli_activity_log', 'batch_uuid'))->toBeFalse();
});

test('manual logs keep their own old key in properties', function () {
    $roles = insertV4Activity(null, ['old' => ['admin'], 'new' => ['bestuur']]);
    $derived = insertV4Activity(null, ['added' => ['bestuur'], 'removed' => []]);
    $empty = insertV4Activity('created', null);

    activityLogV5Migration()->up();

    expect(json_decode(activityRow($roles)->properties, true))->toEqual(['old' => ['admin'], 'new' => ['bestuur']])
        ->and(activityRow($roles)->attribute_changes)->toBeNull()
        ->and(json_decode(activityRow($derived)->properties, true))->toEqual(['added' => ['bestuur'], 'removed' => []])
        ->and(activityRow($empty)->attribute_changes)->toBeNull();
});

test('down puts the changes back into properties', function () {
    $id = insertV4Activity('updated', ['attributes' => ['email' => 'b@x.nl'], 'old' => ['email' => 'a@x.nl']]);

    activityLogV5Migration()->up();
    activityLogV5Migration()->down();

    expect(json_decode(activityRow($id)->properties, true))->toEqual([
        'attributes' => ['email' => 'b@x.nl'],
        'old' => ['email' => 'a@x.nl'],
    ])
        ->and(Schema::hasColumn('soli_activity_log', 'attribute_changes'))->toBeFalse()
        ->and(Schema::hasColumn('soli_activity_log', 'batch_uuid'))->toBeTrue();
});

test('up refuses to drop batch_uuid while a row uses it', function () {
    DB::table('soli_activity_log')->insert([
        'description' => 'batched',
        'batch_uuid' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => activityLogV5Migration()->up())->toThrow(RuntimeException::class);
});
