<?php

namespace App\Console\Commands;

use App\Services\Sad\SadApiClient;
use Illuminate\Console\Command;

/**
 * Capture lid_info.php as a test fixture with every value replaced.
 *
 * The parser matches on labels, and those labels only exist on the live page — the
 * hand-written fixtures in the test suite invented them ("Geboortedatum", "Woonplaats")
 * and so confirmed the parser's own assumptions while the real labels ("Geboorte datum",
 * "Plaats") went unparsed for months. A fixture taken from the real page is the only
 * thing that can catch that, but the real page is full of PII.
 *
 * So: keep the structure and the first cell of every row (the label), replace every
 * other text node with a placeholder. Read the result before committing it.
 *
 * Local only. Capturing writes a file holding what was, moments earlier, one member's
 * personal data, and a server is the wrong place to leave that lying around. Point a
 * local .env at SAD to run this.
 */
class CaptureSadFixture extends Command
{
    protected $signature = 'sad:capture-fixture
        {lid_id : Any member — their values are discarded, only the page shape is kept}
        {--output=tests/fixtures/sad-lid-info.html}';

    protected $description = 'Capture an anonymised lid_info.php fixture for the parser contract test';

    public function handle(SadApiClient $client): int
    {
        if (app()->isProduction()) {
            $this->error('sad:capture-fixture does not run on production.');
            $this->line('Capturing leaves a file of personal data on the server, and the');
            $this->line('fixture belongs in tests/, which is not deployed. Run it locally');
            $this->line('with SAD_BASE_URL, SAD_USERNAME and SAD_PASSWORD in your .env.');

            return self::FAILURE;
        }

        if (! config('services.sad.username') || ! config('services.sad.password')) {
            $this->error('SAD_USERNAME and SAD_PASSWORD must be set to capture a fixture.');

            return self::FAILURE;
        }

        $lidId = (int) $this->argument('lid_id');

        $client->login();
        $html = $client->getMemberPiiHtml($lidId);

        $scrubbed = $this->scrub($html);

        if ($this->looksUnscrubbed($scrubbed)) {
            $this->error('Scrubbing left original values behind — not writing the fixture.');

            return self::FAILURE;
        }

        $path = base_path($this->option('output'));
        file_put_contents($path, $scrubbed);

        $missing = array_diff(array_keys(self::PLACEHOLDERS), $this->recognisedFields);

        $this->info("Wrote {$this->option('output')}.");
        $this->line('Recognised: '.(implode(', ', array_unique($this->recognisedFields)) ?: 'none'));

        if ($missing) {
            $this->error('No label on this page maps to: '.implode(', ', $missing));
            $this->line('Either SAD renamed it or the parser has drifted — fix that before committing.');
        }

        if ($this->unrecognisedLabels) {
            $this->line('Ignored labels: '.implode(', ', array_unique(array_filter($this->unrecognisedLabels))));
        }
        $this->warn('Read it before committing: it came from a page containing personal data.');

        return self::SUCCESS;
    }

    /**
     * Replace the text of every cell except the first of each row. The first cell is
     * the label the parser matches on; everything else is this member's data.
     *
     * Values are fictional but plausible per label, so the contract test can assert
     * that a label maps to the right field rather than merely to something. A label
     * the parser does not know gets the neutral placeholder and is reported.
     */
    private function scrub(string $html): string
    {
        return preg_replace_callback(
            '/<tr[^>]*>(.*?)<\/tr>/si',
            function (array $row): string {
                $cellIndex = 0;
                $label = $this->labelOf($row[0]);

                return preg_replace_callback(
                    '/(<t[dh][^>]*>)(.*?)(<\/t[dh]>)/si',
                    function (array $cell) use (&$cellIndex, $label): string {
                        $value = $cellIndex++ === 0
                            ? $cell[2]
                            : $this->placeholderFor($label);

                        return $cell[1].$value.$cell[3];
                    },
                    $row[0],
                );
            },
            $html,
        );
    }

    /**
     * Cheap safety net: the scrubbed page must not still contain long runs of text
     * from outside the label cells, and must be meaningfully shorter than the original.
     */
    private function looksUnscrubbed(string $scrubbed): bool
    {
        if (! str_contains($scrubbed, self::PLACEHOLDER)) {
            return true;
        }

        return preg_match('/@|\b\d{2}-\d{2}-\d{4}\b|\b\d{4}\s?[A-Z]{2}\b|\b0\d{8,9}\b/u', strip_tags($scrubbed)) === 1;
    }

    private const PLACEHOLDER = 'XXX';

    /** Fictional stand-ins, keyed by the field SadDataParser should map the label to. */
    private const PLACEHOLDERS = [
        'adres' => 'Dorpsstraat 10',
        'postcode' => '1985 AA',
        'plaats' => 'Driehuis',
        'telefoon' => '0612345678',
        'geboortedatum' => '15-03-1990',
        'instrument' => 'Trompet',
    ];

    private function labelOf(string $row): string
    {
        if (! preg_match('/<t[dh][^>]*>(.*?)<\/t[dh]>/si', $row, $cell)) {
            return '';
        }

        $label = html_entity_decode(strip_tags($cell[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return strtolower(preg_replace('/[\s\x{00A0}]+/u', '', $label));
    }

    private function placeholderFor(string $label): string
    {
        $field = match (true) {
            str_contains($label, 'adres') => 'adres',
            str_contains($label, 'postcode') => 'postcode',
            str_contains($label, 'plaats') => 'plaats',
            str_contains($label, 'telefoon') || str_contains($label, 'tel') => 'telefoon',
            str_contains($label, 'geboorte') || str_contains($label, 'geboren') => 'geboortedatum',
            str_contains($label, 'instrument') => 'instrument',
            default => null,
        };

        if ($field === null) {
            $this->unrecognisedLabels[] = $label;

            return self::PLACEHOLDER;
        }

        $this->recognisedFields[] = $field;

        return self::PLACEHOLDERS[$field];
    }

    /** @var string[] */
    private array $unrecognisedLabels = [];

    /** @var string[] */
    private array $recognisedFields = [];
}
