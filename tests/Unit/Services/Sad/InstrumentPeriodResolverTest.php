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
