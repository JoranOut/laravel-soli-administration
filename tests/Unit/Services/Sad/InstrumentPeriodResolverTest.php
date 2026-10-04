<?php

use App\Services\Sad\InstrumentPeriodResolver;

function period(string $naam, ?string $van, ?string $tot = null): array
{
    return ['naam' => $naam, 'van' => $van, 'tot' => $tot];
}

function resolved(array $onderdelen, array $instrumenten): array
{
    return collect(InstrumentPeriodResolver::resolve($onderdelen, $instrumenten))
        ->map(fn ($p) => "{$p['onderdeel']}/{$p['instrument']} {$p['van']}..{$p['tot']}")
        ->sort()->values()->all();
}

test('a new instrument goes to the most recently joined onderdeel, not to the older one', function () {
    $result = resolved(
        [period('marsorkest', '2025-01-01'), period('harmonie', '2027-01-01')],
        [period('trompet', '2025-01-01'), period('trombone', '2028-01-01')],
    );

    // The trombone never reaches the marsorkest: by 2028 the harmonie was the most recent
    expect($result)->toBe([
        'harmonie/trombone 2028-01-01..',
        'harmonie/trompet 2027-01-01..',
        'marsorkest/trompet 2025-01-01..',
    ]);
});

test('two instruments run alongside each other, each with its own period', function () {
    $result = resolved(
        [period('harmonie', '2006-01-01', '2022-01-01')],
        [period('trompet', '2006-01-01', '2022-01-01'), period('trombone', '2012-01-01', '2022-01-01')],
    );

    expect($result)->toBe([
        'harmonie/trombone 2012-01-01..2022-01-01',
        'harmonie/trompet 2006-01-01..2022-01-01',
    ]);
});

test('an instrument ending while its onderdeel continues simply closes', function () {
    $result = resolved(
        [period('marsorkest', '2025-01-01')],
        [period('trompet', '2025-01-01', '2027-01-01'), period('trombone', '2027-01-01')],
    );

    // No separate rule needed — the pairing ends and the next one opens
    expect($result)->toBe([
        'marsorkest/trombone 2027-01-01..',
        'marsorkest/trompet 2025-01-01..2027-01-01',
    ]);
});

test('an instrument that predates every onderdeel lands on the first one', function () {
    $result = resolved(
        [period('harmonie', '2025-01-01')],
        [period('trompet', '2024-01-01')],
    );

    // Clipped to the onderdeel: the member was not in it yet in 2024
    expect($result)->toBe(['harmonie/trompet 2025-01-01..']);
});

test('an undated instrument reaches every onderdeel', function () {
    $result = resolved(
        [period('marsorkest', '2020-01-01'), period('harmonie', '2025-01-01')],
        [period('trompet', null)],
    );

    expect($result)->toBe([
        'harmonie/trompet 2025-01-01..',
        'marsorkest/trompet 2020-01-01..',
    ]);
});

test('two onderdelen joined on the same day both take the instrument', function () {
    $result = resolved(
        [period('marsorkest', '2025-01-01'), period('harmonie', '2025-01-01')],
        [period('trompet', '2026-01-01')],
    );

    expect($result)->toBe([
        'harmonie/trompet 2026-01-01..',
        'marsorkest/trompet 2026-01-01..',
    ]);
});

test('an instrument that ended before the onderdeel began is dropped', function () {
    expect(resolved(
        [period('harmonie', '2025-01-01')],
        [period('trompet', '2010-01-01', '2012-01-01')],
    ))->toBe([]);
});

test('a closed onderdeel keeps the instrument it had, clipped to its own end', function () {
    $result = resolved(
        [period('marsorkest', '2020-01-01', '2024-01-01'), period('harmonie', '2024-01-01')],
        [period('trompet', '2020-01-01')],
    );

    expect($result)->toBe([
        'harmonie/trompet 2024-01-01..',
        'marsorkest/trompet 2020-01-01..2024-01-01',
    ]);
});

test('two overlapping memberships of one onderdeel yield one period, not two', function () {
    // SAD lists HA twice, and splitting "HAMO" makes this more common still. Both
    // clip to the instrument's start, differing only in where they end.
    $result = resolved(
        [period('HA', '2020-01-01', '2030-01-01'), period('HA', '2020-01-01')],
        [period('Trompet', '2025-01-01')],
    );

    expect($result)->toBe(['HA/Trompet 2025-01-01..']);
});

test('periods that touch are joined', function () {
    $result = resolved(
        [period('HA', '2010-01-01', '2015-01-01'), period('HA', '2015-01-01')],
        [period('Trompet', null)],
    );

    expect($result)->toBe(['HA/Trompet 2010-01-01..']);
});

test('periods with a real gap stay apart', function () {
    $result = resolved(
        [period('HA', '2010-01-01', '2012-01-01'), period('HA', '2020-01-01')],
        [period('Trompet', null)],
    );

    expect($result)->toBe([
        'HA/Trompet 2010-01-01..2012-01-01',
        'HA/Trompet 2020-01-01..',
    ]);
});

test('merging does not join different instruments', function () {
    $result = resolved(
        [period('HA', '2020-01-01')],
        [period('Trompet', '2020-01-01', '2024-01-01'), period('Trombone', '2024-01-01')],
    );

    expect($result)->toBe([
        'HA/Trombone 2024-01-01..',
        'HA/Trompet 2020-01-01..2024-01-01',
    ]);
});

/**
 * The database enforces unique (relatie_id, onderdeel_id, instrument_soort_id, van).
 * The resolver has to honour that for any input SAD can produce, not just the shapes
 * someone thought to write a test for — 24 members failed on exactly this, from data
 * nobody had imagined: two memberships of one onderdeel that clip to the same start.
 */
test('no input produces two periods with the same onderdeel, instrument and start', function () {
    mt_srand(20261004);

    $dates = [null, '2010-01-01', '2015-06-30', '2020-01-01', '2020-01-01', '2024-12-31'];
    $pick = fn () => $dates[mt_rand(0, count($dates) - 1)];

    for ($run = 0; $run < 400; $run++) {
        $onderdelen = [];
        $instrumenten = [];

        foreach (range(1, mt_rand(1, 4)) as $i) {
            $onderdelen[] = period(['HA', 'MO', 'KO'][mt_rand(0, 2)], $pick(), $pick());
        }

        foreach (range(1, mt_rand(1, 4)) as $i) {
            $instrumenten[] = period(['Trompet', 'Trombone'][mt_rand(0, 1)], $pick(), $pick());
        }

        $pairs = App\Services\Sad\InstrumentPeriodResolver::resolve($onderdelen, $instrumenten);

        $keys = array_map(fn ($p) => "{$p['onderdeel']}|{$p['instrument']}|{$p['van']}", $pairs);

        expect($keys)->toBe(array_unique($keys),
            'duplicate key for: '.json_encode(['onderdeel' => $onderdelen, 'instrument' => $instrumenten]));
    }
});

test('no input produces two overlapping periods of the same pairing', function () {
    mt_srand(20261005);

    $dates = [null, '2008-01-01', '2012-01-01', '2018-01-01', '2023-01-01'];
    $pick = fn () => $dates[mt_rand(0, count($dates) - 1)];

    for ($run = 0; $run < 400; $run++) {
        $onderdelen = [];
        $instrumenten = [];

        foreach (range(1, mt_rand(1, 4)) as $i) {
            $onderdelen[] = period(['HA', 'MO'][mt_rand(0, 1)], $pick(), $pick());
        }

        foreach (range(1, mt_rand(1, 3)) as $i) {
            $instrumenten[] = period('Trompet', $pick(), $pick());
        }

        $grouped = [];

        foreach (App\Services\Sad\InstrumentPeriodResolver::resolve($onderdelen, $instrumenten) as $p) {
            $grouped[$p['onderdeel'].'|'.$p['instrument']][] = $p;
        }

        foreach ($grouped as $group) {
            usort($group, fn ($a, $b) => [$a['van'] === null ? 0 : 1, $a['van']] <=> [$b['van'] === null ? 0 : 1, $b['van']]);

            for ($i = 1; $i < count($group); $i++) {
                $previousEnd = $group[$i - 1]['tot'];

                expect($previousEnd)->not->toBeNull('an open period is followed by another');
                expect($group[$i]['van'])->toBeGreaterThan($previousEnd);
            }
        }
    }
});
