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
     * Parse the HTML table from lid_info.php into a structured array.
     *
     * Returns array with keys: adres, postcode, plaats, telefoon, geboortedatum, instrument
     */
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

            $label = strtolower(trim(strip_tags($cells[1][0])));
            $value = trim(strip_tags($cells[1][1]));

            if ($value === '') {
                continue;
            }

            match (true) {
                str_contains($label, 'adres') => $result['adres'] = $value,
                str_contains($label, 'postcode') => $result['postcode'] = $value,
                str_contains($label, 'plaats') || str_contains($label, 'woonplaats') => $result['plaats'] = $value,
                str_contains($label, 'telefoon') || str_contains($label, 'tel') => $result['telefoon'] = $value,
                str_contains($label, 'geboortedatum') || str_contains($label, 'geboren') => $result['geboortedatum'] = $value,
                str_contains($label, 'instrument') => $result['instrument'] = $value,
                default => null,
            };
        }

        return $result;
    }
}
