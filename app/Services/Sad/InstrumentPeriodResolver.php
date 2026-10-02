<?php

namespace App\Services\Sad;

/**
 * Work out which instrument belongs to which onderdeel, and for how long.
 *
 * SAD records the two independently: a list of onderdeel periods and a list of
 * instrument periods, with no link between them. The rule that ties them together:
 *
 *   A new instrument goes to the onderdeel most recently joined at that moment,
 *   and to every onderdeel joined after it.
 *
 * So a member who joins the marsorkest in 2025, gets a trumpet in 2025, joins the
 * harmonie in 2027 and gets a trombone in 2028 ends up with the trumpet on the
 * marsorkest and the trombone on the harmonie — the trombone never reaches the
 * marsorkest, because by then the harmonie was the most recent.
 *
 * Instruments are not mutually exclusive: a second one that SAD leaves open runs
 * alongside the first. The period of each pairing is simply where the two overlap,
 * which is why an instrument ending while its onderdeel continues needs no rule of
 * its own — the pairing ends, and a later instrument opens its own.
 */
class InstrumentPeriodResolver
{
    /**
     * @param  array<int, array{van: ?string, tot: ?string, naam: string}>  $onderdelen
     * @param  array<int, array{van: ?string, tot: ?string, naam: string}>  $instrumenten
     * @return array<int, array{onderdeel: string, instrument: string, van: ?string, tot: ?string}>
     */
    public static function resolve(array $onderdelen, array $instrumenten): array
    {
        $pairs = [];

        foreach ($instrumenten as $instrument) {
            foreach (self::onderdelenFor($instrument, $onderdelen) as $onderdeel) {
                $van = self::later($instrument['van'], $onderdeel['van']);
                $tot = self::earlier($instrument['tot'], $onderdeel['tot']);

                // No overlap at all: the instrument ended before this onderdeel began
                if ($van !== null && $tot !== null && $van > $tot) {
                    continue;
                }

                $pairs[] = [
                    'onderdeel' => $onderdeel['naam'],
                    'instrument' => $instrument['naam'],
                    'van' => $van,
                    'tot' => $tot,
                ];
            }
        }

        return $pairs;
    }

    /**
     * The onderdeel that was most recently joined when this instrument arrived, plus
     * every onderdeel joined afterwards. A tie on the same date takes both.
     *
     * @param  array{van: ?string, tot: ?string, naam: string}  $instrument
     * @param  array<int, array{van: ?string, tot: ?string, naam: string}>  $onderdelen
     * @return array<int, array{van: ?string, tot: ?string, naam: string}>
     */
    private static function onderdelenFor(array $instrument, array $onderdelen): array
    {
        $start = $instrument['van'];

        // An undated instrument has no "moment", so it reaches every onderdeel
        if ($start === null) {
            return $onderdelen;
        }

        $later = array_values(array_filter(
            $onderdelen,
            fn ($o) => $o['van'] === null || $o['van'] >= $start,
        ));

        $earlier = array_filter($onderdelen, fn ($o) => $o['van'] !== null && $o['van'] < $start);

        if ($earlier) {
            $mostRecent = max(array_column($earlier, 'van'));
            $later = array_merge(
                $later,
                array_values(array_filter($earlier, fn ($o) => $o['van'] === $mostRecent)),
            );
        }

        return $later;
    }

    private static function later(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return max($a, $b);
    }

    private static function earlier(?string $a, ?string $b): ?string
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        return min($a, $b);
    }
}
