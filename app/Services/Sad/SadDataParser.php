<?php

namespace App\Services\Sad;

use App\Models\InstrumentSoort;
use Carbon\Carbon;

class SadDataParser
{
    /**
     * Split a raw phone string into individual numbers.
     *
     * Handles semicolons, commas, slashes, Dutch "en", and two numbers
     * separated by a space (e.g. "0255-534403 06-11052119").
     */
    /**
     * SAD's own instrument vocabulary, shared by the file import and the scrape sync.
     *
     * It lived in ImportSadMembers alone, so the sync — which resolves an instrument
     * by literal name — never saw it: "fluit" failed to match "Dwarsfluit" while the
     * translation sat twenty lines away in the same project.
     *
     * TYPE_MAP holds values that are roles rather than instruments, SKIP_INSTRUMENTS
     * values that mean "none at all". Neither is a fault worth warning about.
     */
    public const TYPE_MAP = [
        'dirigent' => 'dirigent', 'dirigent klein orkes' => 'dirigent',
        'dirigent sa' => 'dirigent', 'dirigent samenspelkl' => 'dirigent',
        'instructeur' => 'dirigent', 'instructeur slagwerk' => 'dirigent',
        'instructie' => 'dirigent', 'instrukt' => 'dirigent', 'instrukteu' => 'dirigent',
        'docent' => 'docent', 'docent klarinet' => 'docent', 'docent mos' => 'docent', 'mos' => 'docent',
        '05-12-2021    docent dwarsfluit' => 'docent',
        'begeleider' => 'vrijwilliger', 'begeleiding' => 'vrijwilliger', 'stofzuiger' => 'vrijwilliger',
    ];

    public const INSTRUMENT_MAP = [
        // Klarinet
        'bes klarin' => 'Besklarinet', 'bes klarinet' => 'Besklarinet', 'besklarinet' => 'Besklarinet',
        'klarinet' => 'Klarinet', 'klarinet (eigen)' => 'Klarinet',
        'klarinet / saxofoon' => ['Klarinet', 'Saxofoon'],
        'klarinet bariton sax' => ['Klarinet', 'Baritonsaxofoon'],
        'alt klarin' => 'Altklarinet',
        'bas klarin' => 'Basklarinet', 'bas klarinet' => 'Basklarinet', 'basklarinet' => 'Basklarinet',
        'bas clar' => 'Basklarinet', 'bas clarin' => 'Basklarinet',
        'es klarin' => 'Esklarinet', 'es klarine' => 'Esklarinet', 'es klarinet' => 'Esklarinet',
        '(contra)basklarinet' => 'Basklarinet',

        // Saxofoon
        'saxofoon' => 'Saxofoon', 'saxofoon (kinder)' => 'Saxofoon', 'saxofoon (soli)' => 'Saxofoon',
        'saxofoon trombone' => ['Saxofoon', 'Trombone'],
        'alt sax' => 'Altsaxofoon', 'alt saxofo' => 'Altsaxofoon', 'alt saxofoon' => 'Altsaxofoon',
        'altsax' => 'Altsaxofoon', 'altsax (eigen)' => 'Altsaxofoon', 'altsaxofoon' => 'Altsaxofoon',
        'altsax en paradetrom' => ['Altsaxofoon', 'Paradetrom'],
        'alt/tensax' => ['Altsaxofoon', 'Tenorsaxofoon'],
        '14-11-2017    tenor sax en klarine' => ['Tenorsaxofoon', 'Klarinet'],
        'tenor sax' => 'Tenorsaxofoon', 'tenor saxofoon' => 'Tenorsaxofoon',
        'tenorsax' => 'Tenorsaxofoon', 'tenorsax (eigen inst' => 'Tenorsaxofoon',
        'tenorsaxofoon' => 'Tenorsaxofoon',
        'tenor/altsaxofoon' => ['Tenorsaxofoon', 'Altsaxofoon'],
        'bariton saxofoon' => 'Baritonsaxofoon', 'baritonsax' => 'Baritonsaxofoon',
        'sopraan saxofoon' => 'Sopraansaxofoon',

        // Dwarsfluit
        'fluit' => 'Dwarsfluit', 'dwarsfluit' => 'Dwarsfluit', 'eigen dwarsfluit' => 'Dwarsfluit',
        'dwarsfl' => 'Dwarsfluit', '07-09-2018    dwarsfluit' => 'Dwarsfluit',
        'dwarsfluit (eigen in' => 'Dwarsfluit', 'dwarsfluit nu nog el' => 'Dwarsfluit',
        '01-10-2017    klarinet' => 'Klarinet',
        'fluit fag' => ['Dwarsfluit', 'Fagot'],
        'fluit/saxofoon' => ['Dwarsfluit', 'Saxofoon'],
        'piccolo' => 'Piccolo',
        'piccolo/fl' => ['Piccolo', 'Dwarsfluit'],

        // Koper — trompet
        'trompet' => 'Trompet', 'trompet (eigen)' => 'Trompet',
        'trompet slagwerk' => ['Trompet', 'Slagwerk'],
        'cornet / trompet' => ['Cornet', 'Trompet'],
        'cornet' => 'Cornet', 'piston' => 'Trompet',

        // Koper — trombone
        'trombone' => 'Trombone',
        'bas trombone' => 'Bastrombone', 'bastrombone' => 'Bastrombone',

        // Koper — hoorn / althoorn / bugel
        'hoorn' => 'Hoorn', 'althoorn' => 'Althoorn',
        'bugel' => 'Bugel',
        'tuba' => 'Tuba', 'sousafoon' => 'Sousafoon',
        'bes bas' => 'Besbas', 'besbas' => 'Besbas',
        'bes bas trompet' => ['Besbas', 'Trompet'],
        'es bas' => 'Esbas', 'bas' => 'Tuba',
        'contrabas' => 'Contrabas', 'bassist' => 'Contrabas', 'bas gitaar' => 'Basgitaar',

        // Koper — bariton / euphonium
        'bariton' => 'Bariton',
        'bariton bas' => ['Bariton', 'Tuba'],
        'euphonium' => 'Euphonium',

        // Houtblazers
        'hobo' => 'Hobo',
        'hobo/alt h' => ['Hobo', 'Althoorn'],
        'fagot' => 'Fagot', 'fagot (eigen)' => 'Fagot',

        // Slagwerk
        'slagwerk' => 'Slagwerk', 'slaginstrument' => 'Slagwerk',
        'drum' => 'Drumstel', 'drums' => 'Drumstel', 'drumstel' => 'Drumstel',
        'overslagtr' => 'Slagwerk',
        'slagwerk / saxofoon' => ['Slagwerk', 'Saxofoon'],
        'mel sw' => 'Melodisch slagwerk', 'mel. slagw' => 'Melodisch slagwerk',
        'mel sw (ha) + fagot' => ['Melodisch slagwerk', 'Fagot'],
        'melodisch slagwerk' => 'Melodisch slagwerk', 'melodisch slagwerk e' => 'Melodisch slagwerk',
        'paradetrom' => 'Paradetrom', 'kleine trom' => 'Kleine trom',
        'trom' => 'Trom', 'trommel' => 'Trom', 'trio tom' => 'Trio tom', 'trio tom t' => 'Trio tom',
        'bekken' => 'Bekken', 'pauken' => 'Pauken',
        'marimba' => 'Marimba', 'vibrafoon' => 'Vibrafoon', 'xylofoon' => 'Xylofoon',
        'buisklokken' => 'Buisklokken', 'bells' => 'Buisklokken',
        'klokkenspel' => 'Klokkenspel', 'klokkenspiel' => 'Klokkenspel',
        'tamboer maitre' => 'Tamboer-maître', 'tambourmaitre' => 'Tamboer-maître',

        // Majorette / twirl
        'majorette' => 'Majorette', 'baton' => 'Majorette', 'twirlteam' => 'Majorette',
        'vlaggenw' => 'Vlaggenwacht', 'vlaggew' => 'Vlaggenwacht', 'vlaggewach' => 'Vlaggenwacht',

        // Toetsen
        'keyboard' => 'Keyboard', 'piano' => 'Piano', 'orgel' => 'Orgel',

        // Diverse
        'harp' => 'Harp', 'strijk' => 'Strijk',

        // Overig
        'gitaar' => 'Gitaar',
        'zang' => 'Zang', 'zangeres' => 'Zang',
    ];

    public const SKIP_INSTRUMENTS = ['oud goud', 'geen', 'niet spelend bestuur'];

    public static function splitPhoneNumbers(string $telefoon): array
    {
        $parts = preg_split('/\s*[;,\/]\s*|\s+en\s+/', $telefoon);

        $numbers = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // Detect two phone numbers separated only by a space (e.g. "0255754827 0641143745")
            if (preg_match('/^(0[\d\-]+)\s+(0[\d\-]+)$/', $part, $m)) {
                $numbers[] = trim($m[1]);
                $numbers[] = trim($m[2]);
            } else {
                $numbers[] = $part;
            }
        }

        // Strip trailing parenthetical notes like "(moe..."
        return array_values(array_filter(array_map(function ($n) {
            return trim(preg_replace('/\s*\(.*$/', '', $n));
        }, $numbers), fn ($n) => $n !== ''));
    }

    /**
     * Split an address string into [straat, huisnummer, toevoeging].
     */
    public static function splitAddress(string $address): array
    {
        if (preg_match('/^(.+?)\s+(\d+)\s*(.*)$/', $address, $m)) {
            return [
                trim($m[1]),
                trim($m[2]),
                trim($m[3]) ?: null,
            ];
        }

        // No house number found — store entire string as street with empty huisnummer
        return [$address, '', null];
    }

    /**
     * Match a raw instrument name against InstrumentSoort records.
     *
     * Returns the instrument_soort_id or null if no match found.
     */
    /**
     * The instrument names a raw SAD value stands for.
     *
     * Empty means there is nothing to record and nothing to complain about: a blank
     * field, a value that means "none", or a role such as dirigent. One value can
     * stand for two instruments ("fluit fag"). An unknown value is returned as-is so
     * the caller can still try a literal match before it warns.
     *
     * @return string[]
     */
    public static function instrumentNamesFor(string $raw): array
    {
        $key = strtolower(trim($raw));

        if ($key === '' || in_array($key, self::SKIP_INSTRUMENTS, true) || isset(self::TYPE_MAP[$key])) {
            return [];
        }

        $mapped = self::INSTRUMENT_MAP[$key] ?? null;

        if ($mapped === null) {
            return [trim($raw)];
        }

        return is_array($mapped) ? $mapped : [$mapped];
    }

    public static function matchInstrumentSoort(string $instrumentName, array $instrumentSoortLookup): ?int
    {
        $normalized = strtolower(str_replace(' ', '', $instrumentName));

        foreach ($instrumentSoortLookup as $id => $naam) {
            if (strtolower(str_replace(' ', '', $naam)) === $normalized) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Parse a DD-MM-YYYY date string to Y-m-d format.
     */
    public static function parseDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('d-m-Y', $date);

            if ($parsed->year < 1900 || $parsed->year > 2100) {
                return null;
            }

            return $parsed->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * SAD's labels carry spacing that varies per row ("Geboorte datum", "&nbsp;Plaats").
     * Strip every kind of whitespace so matching is on the word alone, not its layout.
     */
    private static function normalizeLabel(string $cell): string
    {
        $label = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return strtolower(preg_replace('/[\s\x{00A0}]+/u', '', $label));
    }

    /**
     * An empty SAD cell renders as "&nbsp;", which trim() leaves in place — decode
     * first so a blank field is recognised as blank instead of stored as whitespace.
     */
    private static function normalizeValue(string $cell): string
    {
        $value = html_entity_decode(strip_tags($cell), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value));
    }

    /**
     * Parse the HTML table from lid_info.php into a structured array.
     *
     * Returns array with keys: adres, postcode, plaats, telefoon, geboortedatum, instrument
     */
    /**
     * Parse l_tinfo.php — one member's full history, in sections separated by a run
     * of underscores. Only the dated sections are read here.
     *
     * This page is why the sync no longer has to guess: lid_info.php shows a single
     * undated instrument, while this one lists every period with van and tot. The
     * scrape that produced the original import read the same page.
     *
     * @return array{onderdeel: array<int, array{van: ?string, tot: ?string, naam: string}>, instrument: array<int, array{van: ?string, tot: ?string, naam: string}>}
     */
    public static function parseMemberHistoryHtml(string $html): array
    {
        return [
            'onderdeel' => self::parseHistorySection($html, 'Onderdeel'),
            'instrument' => self::parseHistorySection($html, 'Instrument'),
        ];
    }

    /**
     * @return array<int, array{van: ?string, tot: ?string, naam: string}>
     */
    private static function parseHistorySection(string $html, string $heading): array
    {
        $parts = preg_split('/'.preg_quote($heading, '/').'/', $html, 2);

        if (count($parts) < 2) {
            return [];
        }

        $section = preg_split('/_{5,}/', $parts[1], 2)[0];
        $rows = [];

        if (! preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $section, $matches)) {
            return $rows;
        }

        foreach ($matches[1] as $row) {
            if (! preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cells) || count($cells[1]) < 3) {
                continue;
            }

            $naam = self::normalizeValue($cells[1][2]);

            if ($naam === '') {
                continue;
            }

            $rows[] = [
                'van' => self::parseDate(self::normalizeValue($cells[1][0])),
                'tot' => self::parseDate(self::normalizeValue($cells[1][1])),
                'naam' => $naam,
            ];
        }

        return $rows;
    }

    public static function parsePiiHtml(string $html): array
    {
        $result = [
            'adres' => null,
            'postcode' => null,
            'plaats' => null,
            'telefoon' => null,
            'geboortedatum' => null,
            'instrument' => null,
        ];

        // Extract table rows
        if (! preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $html, $rows)) {
            return $result;
        }

        foreach ($rows[1] as $row) {
            if (! preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $row, $cells) || count($cells[1]) < 2) {
                continue;
            }

            $label = self::normalizeLabel($cells[1][0]);
            $value = self::normalizeValue($cells[1][1]);

            if ($value === '') {
                continue;
            }

            match (true) {
                str_contains($label, 'adres') => $result['adres'] = $value,
                str_contains($label, 'postcode') => $result['postcode'] = $value,
                str_contains($label, 'plaats') || str_contains($label, 'woonplaats') => $result['plaats'] = $value,
                str_contains($label, 'telefoon') || str_contains($label, 'tel') => $result['telefoon'] = $value,
                str_contains($label, 'geboorte') || str_contains($label, 'geboren') => $result['geboortedatum'] = $value,
                str_contains($label, 'instrument') => $result['instrument'] = $value,
                default => null,
            };
        }

        return $result;
    }
}
