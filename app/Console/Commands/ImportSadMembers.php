<?php

namespace App\Console\Commands;

use App\Jobs\SyncGoogleContactsJob;
use App\Models\InstrumentSoort;
use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieInstrument;
use App\Models\RelatieType;
use App\Observers\GoogleContactSyncObserver;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportSadMembers extends Command
{
    protected $signature = 'import:sad-members
        {path : Path to members.json}
        {--dry-run : Validate and show stats without inserting}
        {--fresh : Clear existing relatie data before import}';

    protected $description = 'Import members from SAD system JSON export';

    private Collection $instrumentSoortLookup;

    /**
     * Known base codes for onderdeel decomposition.
     * 3-letter codes listed first so the recursive decomposer tries them before 2-letter prefixes.
     */
    private const BASE_CODES = [
        'TAK',
        'BB', 'DF', 'FB', 'HA', 'KK', 'KO', 'LL', 'MA', 'MO',
        'OG', 'OK', 'OL', 'OP', 'OU', 'OV', 'SA', 'SG', 'SK',
        'SV', 'TA', 'TK', 'TW', 'VL',
    ];

    /**
     * Raw SAD instrument name (lowercased) → normalized instrument name(s).
     * String = single instrument, array = multiple instruments (person plays both).
     *
     * @var array<string, string|string[]>
     */
    private const INSTRUMENT_MAP = [
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

    /** Raw SAD instrument name (lowercased) → relatie type name. */
    private const TYPE_MAP = [
        'dirigent' => 'dirigent', 'dirigent klein orkes' => 'dirigent',
        'dirigent sa' => 'dirigent', 'dirigent samenspelkl' => 'dirigent',
        'instructeur' => 'dirigent', 'instructeur slagwerk' => 'dirigent',
        'instructie' => 'dirigent', 'instrukt' => 'dirigent', 'instrukteu' => 'dirigent',
        'docent' => 'docent', 'docent klarinet' => 'docent', 'docent mos' => 'docent', 'mos' => 'docent',
        '05-12-2021    docent dwarsfluit' => 'docent',
        'begeleider' => 'vrijwilliger', 'begeleiding' => 'vrijwilliger', 'stofzuiger' => 'vrijwilliger',
    ];

    /** Values that are neither instrument nor type — skip silently. */
    private const SKIP_INSTRUMENTS = ['oud goud', 'geen', 'niet spelend bestuur'];

    public function handle(): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $raw = file_get_contents($path);

        // Fix UTF-8 mojibake: the SAD scraper double-encoded UTF-8 characters.
        // e.g. "é" (C3 A9) was read as Latin-1 producing "Ã©" (C3 83 C2 A9).
        // Converting UTF-8 → ISO-8859-1 collapses the multi-byte sequences back
        // to single bytes, which happen to be the original valid UTF-8 bytes.
        $raw = mb_convert_encoding($raw, 'ISO-8859-1', 'UTF-8');

        $members = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        $this->info(sprintf('Found %d members to import.', count($members)));

        // Ensure all onderdelen exist (creates missing historical ones)
        $this->ensureOnderdelen();

        $onderdelen = Onderdeel::whereNotNull('afkorting')->get()->keyBy('afkorting');
        $lidType = RelatieType::where('naam', 'lid')->firstOrFail();
        $this->instrumentSoortLookup = InstrumentSoort::all()->keyBy('naam');

        // Validate every combined code can be fully decomposed
        $errors = $this->validateDecompositions($members, $onderdelen);
        if ($errors->isNotEmpty()) {
            $this->error('Onderdeel decomposition errors:');
            $errors->each(fn ($e) => $this->line("  - {$e}"));

            return self::FAILURE;
        }

        $dryRun = $this->option('dry-run');

        // Handle --fresh
        if ($this->option('fresh')) {
            $adminRelatieIds = Relatie::where('beheerd_in_admin', true)->pluck('id');

            if ($dryRun) {
                $this->warn('[DRY RUN] Would clear existing relatie data (preserving '.$adminRelatieIds->count().' admin-managed relaties).');
            } else {
                $this->warn('Clearing existing relatie data (preserving '.$adminRelatieIds->count().' admin-managed relaties)...');

                $subResourceTables = [
                    'soli_betalingen' => 'te_betalen_contributie_id',
                    'soli_te_betalen_contributies' => 'relatie_id',
                    'soli_relatie_onderdeel' => 'relatie_id',
                    'soli_relatie_relatie_type' => 'relatie_id',
                    'soli_relatie_sinds' => 'relatie_id',
                    'soli_relatie_instrument' => 'relatie_id',
                    'soli_instrument_bespelers' => 'relatie_id',
                    'soli_adressen' => 'relatie_id',
                    'soli_emails' => 'relatie_id',
                    'soli_telefoons' => 'relatie_id',
                    'soli_giro_gegevens' => 'relatie_id',
                    'soli_opleidingen' => 'relatie_id',
                    'soli_uniformen' => 'relatie_id',
                    'soli_insignes' => 'relatie_id',
                    'soli_diplomas' => 'relatie_id',
                    'soli_andere_verenigingen' => 'relatie_id',
                ];

                Schema::disableForeignKeyConstraints();

                if ($adminRelatieIds->isEmpty()) {
                    // No admin-managed relaties — fast truncate
                    foreach (array_keys($subResourceTables) as $table) {
                        DB::table($table)->truncate();
                    }
                    DB::table('soli_relaties')->truncate();
                } else {
                    // Delete only non-admin relatie data
                    // First handle betalingen (linked via te_betalen_contributies, not directly by relatie_id)
                    $nonAdminContributieIds = DB::table('soli_te_betalen_contributies')
                        ->whereNotIn('relatie_id', $adminRelatieIds)
                        ->pluck('id');
                    DB::table('soli_betalingen')->whereIn('te_betalen_contributie_id', $nonAdminContributieIds)->delete();

                    foreach ($subResourceTables as $table => $column) {
                        if ($table === 'soli_betalingen') {
                            continue; // Already handled above
                        }
                        DB::table($table)->whereNotIn($column, $adminRelatieIds)->delete();
                    }
                    DB::table('soli_relaties')->where('beheerd_in_admin', false)->delete();
                }

                Schema::enableForeignKeyConstraints();
            }
        }

        $stats = ['created' => 0, 'matched' => 0, 'skipped' => 0, 'errors' => 0];

        // Disable activity logging and Google Contacts observers during mass import
        activity()->disableLogging();
        GoogleContactSyncObserver::$disabled = true;

        $importFn = function () use ($members, $onderdelen, $lidType, &$stats, $dryRun) {
            $bar = $this->output->createProgressBar(count($members));
            $bar->start();

            foreach ($members as $member) {
                try {
                    $this->importMember($member, $onderdelen, $lidType, $stats, $dryRun);
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->newLine();
                    $this->error(sprintf(
                        'Error importing lid_id %s (%s): %s',
                        $member['lid_id'] ?? '?',
                        $member['naam'] ?? '?',
                        $e->getMessage(),
                    ));
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine(2);
        };

        if ($dryRun) {
            $importFn();
        } else {
            DB::transaction($importFn);
        }

        // Drumfanfare became Klein Orkest — cap all DF memberships at KO's first member date
        $this->capDrumfanfare();

        // Close open onderdelen for ex-members at their lidmaatschap end date
        $this->closeOnderdelenForExMembers();

        // Mark muziekgroepen without any members as inactive
        $this->deactivateEmptyOnderdelen();

        activity()->enableLogging();
        GoogleContactSyncObserver::$disabled = false;

        if (! $dryRun) {
            SyncGoogleContactsJob::dispatch();
        }

        $this->info($dryRun ? 'Dry run complete!' : 'Import complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Created', $stats['created']],
                ['Matched/Updated', $stats['matched']],
                ['Skipped (admin-managed)', $stats['skipped']],
                ['Errors', $stats['errors']],
                ['Total processed', count($members)],
            ],
        );

        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ──────────────────────────────────────────────
    //  Post-import data corrections
    // ──────────────────────────────────────────────

    private function capDrumfanfare(): void
    {
        $df = Onderdeel::where('afkorting', 'DF')->first();
        $ko = Onderdeel::where('naam', 'Klein Orkest')->first();

        if (! $df || ! $ko) {
            return;
        }

        $firstKo = DB::table('soli_relatie_onderdeel')
            ->where('onderdeel_id', $ko->id)
            ->min('van');

        if (! $firstKo) {
            return;
        }

        $adminRelatieIds = Relatie::where('beheerd_in_admin', true)->pluck('id');

        // End open memberships at the KO start date
        $endedOpen = DB::table('soli_relatie_onderdeel')
            ->where('onderdeel_id', $df->id)
            ->whereNull('tot')
            ->whereNotIn('relatie_id', $adminRelatieIds)
            ->update(['tot' => $firstKo]);

        // Cap memberships that extend past the KO start date
        $capped = DB::table('soli_relatie_onderdeel')
            ->where('onderdeel_id', $df->id)
            ->where('tot', '>', $firstKo)
            ->whereNotIn('relatie_id', $adminRelatieIds)
            ->update(['tot' => $firstKo]);

        // Remove memberships that started after KO existed
        $removed = DB::table('soli_relatie_onderdeel')
            ->where('onderdeel_id', $df->id)
            ->where('van', '>=', $firstKo)
            ->whereNotIn('relatie_id', $adminRelatieIds)
            ->delete();

        $total = $endedOpen + $capped + $removed;
        if ($total > 0) {
            $this->info("Drumfanfare → Klein Orkest: {$endedOpen} ended, {$capped} capped, {$removed} removed at {$firstKo}");
        }
    }

    private function closeOnderdelenForExMembers(): void
    {
        $relaties = Relatie::whereHas('relatieSinds')
            ->where('beheerd_in_admin', false)
            ->with(['relatieSinds', 'onderdelen'])
            ->get();

        $pivotsClosed = 0;

        foreach ($relaties as $relatie) {
            if ($relatie->relatieSinds->firstWhere('lid_tot', null)) {
                continue; // still active member
            }

            $latestTot = Carbon::parse($relatie->relatieSinds->max('lid_tot'))->toDateString();

            foreach ($relatie->onderdelen->filter(fn ($o) => ! $o->pivot->tot) as $onderdeel) {
                DB::table('soli_relatie_onderdeel')
                    ->where('id', $onderdeel->pivot->id)
                    ->update(['tot' => $latestTot]);
                $pivotsClosed++;
            }
        }

        if ($pivotsClosed > 0) {
            $this->info("Closed {$pivotsClosed} open onderdeel assignments for ex-members");
        }
    }

    private function deactivateEmptyOnderdelen(): void
    {
        $onderdelen = Onderdeel::where('type', 'muziekgroep')
            ->where('actief', true)
            ->get();

        foreach ($onderdelen as $onderdeel) {
            $hasMembers = DB::table('soli_relatie_onderdeel')
                ->where('onderdeel_id', $onderdeel->id)
                ->exists();

            if (! $hasMembers) {
                $onderdeel->update(['actief' => false]);
                $this->info("Marked inactive (no members): {$onderdeel->naam}");
            }
        }
    }

    // ──────────────────────────────────────────────
    //  Onderdeel bootstrapping
    // ──────────────────────────────────────────────

    private function ensureOnderdelen(): void
    {
        $missing = [
            ['naam' => 'Drumfanfare', 'afkorting' => 'DF', 'type' => 'muziekgroep', 'actief' => false],
            ['naam' => 'Leerlingen', 'afkorting' => 'LL', 'type' => 'muziekgroep', 'actief' => false],
            ['naam' => 'Kennismakingsklas', 'afkorting' => 'KK', 'type' => 'muziekgroep', 'actief' => false],
            ['naam' => 'VL', 'afkorting' => 'VL', 'type' => 'overig', 'actief' => false],
            ['naam' => 'MA', 'afkorting' => 'MA', 'type' => 'overig', 'actief' => false],
            ['naam' => 'TA', 'afkorting' => 'TA', 'type' => 'overig', 'actief' => false],
            ['naam' => 'TK', 'afkorting' => 'TK', 'type' => 'overig', 'actief' => false],
            ['naam' => 'TAK', 'afkorting' => 'TAK', 'type' => 'overig', 'actief' => false],
            ['naam' => 'OP', 'afkorting' => 'OP', 'type' => 'overig', 'actief' => false],
        ];

        foreach ($missing as $data) {
            Onderdeel::firstOrCreate(
                ['afkorting' => $data['afkorting']],
                $data,
            );
        }

        // Update existing Slagwerkklas to have afkorting SK
        Onderdeel::where('naam', 'Slagwerkklas')
            ->whereNull('afkorting')
            ->update(['afkorting' => 'SK']);

        // Update existing Overig to have afkorting OV
        Onderdeel::where('naam', 'Overig')
            ->where('type', 'overig')
            ->whereNull('afkorting')
            ->update(['afkorting' => 'OV']);
    }

    // ──────────────────────────────────────────────
    //  Onderdeel code decomposition
    // ──────────────────────────────────────────────

    private function validateDecompositions(array $members, Collection $onderdelen): Collection
    {
        $errors = collect();

        foreach ($members as $member) {
            foreach ($member['onderdeel'] ?? [] as $record) {
                $code = $record['onderdeel'];
                $baseCodes = $this->decomposeCode($code);

                if ($baseCodes === null) {
                    $errors->push("Cannot decompose '{$code}' for lid_id {$member['lid_id']} ({$member['naam']})");
                    continue;
                }

                foreach ($baseCodes as $bc) {
                    if (! $onderdelen->has($bc)) {
                        $errors->push("Unknown base code '{$bc}' from '{$code}' for lid_id {$member['lid_id']}");
                    }
                }
            }
        }

        return $errors->unique();
    }

    private function decomposeCode(string $code): ?array
    {
        return $this->decomposeRecursive($code, []);
    }

    private function decomposeRecursive(string $remaining, array $found): ?array
    {
        if ($remaining === '') {
            return $found;
        }

        foreach (self::BASE_CODES as $baseCode) {
            if (str_starts_with($remaining, $baseCode)) {
                $result = $this->decomposeRecursive(
                    substr($remaining, strlen($baseCode)),
                    array_merge($found, [$baseCode]),
                );

                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null; // backtrack
    }

    // ──────────────────────────────────────────────
    //  Per-member import
    // ──────────────────────────────────────────────

    private function importMember(
        array $member,
        Collection $onderdelen,
        RelatieType $lidType,
        array &$stats,
        bool $dryRun,
    ): void {
        $lidId = $member['lid_id'];
        $geboortedatum = $this->parseDate($member['gebdat'] ?? null);

        // Active if any lidmaatschap period is still open
        $actief = collect($member['lidmaatschap'] ?? [])
            ->contains(fn ($l) => $l['tot'] === null);

        // 1. Match by relatie_nummer
        $relatie = Relatie::withTrashed()->where('relatie_nummer', $lidId)->first();

        // 2. Match by exact name (only against relaties without a relatie_nummer,
        //    i.e. manually created records — not other SAD imports in this batch)
        if (! $relatie) {
            $voornaam = $member['vnaam'] ?? '';
            $achternaam = $member['anaam'] ?? '';

            if ($voornaam && $achternaam) {
                $candidates = Relatie::withTrashed()
                    ->where('voornaam', $voornaam)
                    ->where('achternaam', $achternaam)
                    ->whereNull('relatie_nummer')
                    ->get();

                if ($candidates->count() === 1) {
                    $relatie = $candidates->first();
                }
            }
        }

        $isNew = false;

        if ($relatie) {
            if ($relatie->beheerd_in_admin) {
                $stats['skipped']++;

                return;
            }

            $stats['matched']++;
            if ($dryRun) {
                return;
            }

            // Set relatie_nummer if matched by name and it was missing
            if (! $relatie->relatie_nummer) {
                $relatie->update(['relatie_nummer' => $lidId]);
            }
        } else {
            $isNew = true;
            $stats['created']++;
            if ($dryRun) {
                return;
            }

            $relatie = Relatie::create([
                'relatie_nummer' => $lidId,
                'voornaam' => $member['vnaam'] ?? '',
                'tussenvoegsel' => $member['tussen'],
                'achternaam' => $member['anaam'] ?? '',
                'geboortedatum' => $geboortedatum,
                'actief' => $actief,
            ]);
        }

        $this->importAdres($relatie, $member);
        $this->importEmails($relatie, $member);
        $this->importTelefoons($relatie, $member);
        $this->importLidmaatschap($relatie, $member);
        $this->importOnderdelen($relatie, $member, $onderdelen);
        $this->importInstrumenten($relatie, $member, $onderdelen);

        // Only assign lid type for new relaties — preserves manual corrections
        // (e.g. removing lid type from a dirigent who has lidmaatschap periods in SAD)
        if ($isNew) {
            $this->attachLidType($relatie, $member, $lidType);
        }
    }

    // ──────────────────────────────────────────────
    //  Contact info
    // ──────────────────────────────────────────────

    private function importAdres(Relatie $relatie, array $member): void
    {
        $raw = trim($member['straat'] ?? '');
        if (! $raw) {
            return;
        }

        [$straat, $huisnummer, $toevoeging] = $this->splitAddress($raw);

        $exists = $relatie->adressen()
            ->where('straat', $straat)
            ->where('huisnummer', $huisnummer)
            ->exists();

        if ($exists) {
            return;
        }

        $relatie->adressen()->create([
            'straat' => $straat,
            'huisnummer' => $huisnummer,
            'huisnummer_toevoeging' => $toevoeging,
            'postcode' => $member['postcode'] ?? null,
            'plaats' => $member['plaats'] ?? null,
        ]);
    }

    private function splitAddress(string $address): array
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

    private function importEmails(Relatie $relatie, array $member): void
    {
        $raw = trim($member['email'] ?? '');
        if (! $raw) {
            return;
        }

        $emails = preg_split('/\s*[;,]\s*/', $raw);

        foreach ($emails as $email) {
            $email = trim($email);
            if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if (! $relatie->emails()->where('email', $email)->exists()) {
                $relatie->emails()->create(['email' => $email]);
            }
        }
    }

    private function importTelefoons(Relatie $relatie, array $member): void
    {
        $raw = trim($member['telefoon'] ?? '');
        if (! $raw) {
            return;
        }

        foreach ($this->splitPhoneNumbers($raw) as $nummer) {
            if (! $nummer) {
                continue;
            }

            if (! $relatie->telefoons()->where('nummer', $nummer)->exists()) {
                $relatie->telefoons()->create(['nummer' => $nummer]);
            }
        }
    }

    private function splitPhoneNumbers(string $telefoon): array
    {
        // Split on semicolons, commas, slashes, Dutch "en"
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

    // ──────────────────────────────────────────────
    //  Lidmaatschap
    // ──────────────────────────────────────────────

    private function importLidmaatschap(Relatie $relatie, array $member): void
    {
        foreach ($member['lidmaatschap'] ?? [] as $period) {
            $lidSinds = $this->parseDate($period['van']);
            $lidTot = $this->parseDate($period['tot']);

            if (! $lidSinds) {
                continue;
            }

            if ($relatie->relatieSinds()->where('lid_sinds', $lidSinds)->exists()) {
                continue;
            }

            $relatie->relatieSinds()->create([
                'lid_sinds' => $lidSinds,
                'lid_tot' => $lidTot,
            ]);
        }
    }

    // ──────────────────────────────────────────────
    //  Onderdelen (decompose + merge)
    // ──────────────────────────────────────────────

    private function importOnderdelen(Relatie $relatie, array $member, Collection $onderdelen): void
    {
        // Fallback van date when an onderdeel record has van=null
        $earliestLidVan = collect($member['lidmaatschap'] ?? [])
            ->map(fn ($l) => $this->parseDate($l['van']))
            ->filter()
            ->sort()
            ->first();

        // Collect per-base-code ranges from SAD records
        $codeRanges = [];
        foreach ($member['onderdeel'] ?? [] as $record) {
            $van = $this->parseDate($record['van']);
            $tot = $this->parseDate($record['tot']);
            $baseCodes = $this->decomposeCode($record['onderdeel']);

            if (! $baseCodes) {
                continue;
            }

            foreach ($baseCodes as $bc) {
                $codeRanges[$bc][] = ['van' => $van, 'tot' => $tot];
            }
        }

        // Merge consecutive ranges and insert pivot records
        foreach ($codeRanges as $code => $ranges) {
            $onderdeel = $onderdelen->get($code);
            if (! $onderdeel) {
                continue;
            }

            $merged = $this->mergeRanges($ranges);

            foreach ($merged as $range) {
                $van = $range['van'] ?? $earliestLidVan;
                if (! $van) {
                    continue; // Cannot insert without a van date
                }

                $exists = DB::table('soli_relatie_onderdeel')
                    ->where('relatie_id', $relatie->id)
                    ->where('onderdeel_id', $onderdeel->id)
                    ->where('van', $van)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $relatie->onderdelen()->attach($onderdeel->id, [
                    'van' => $van,
                    'tot' => $range['tot'],
                ]);
            }
        }
    }

    private function mergeRanges(array $ranges): array
    {
        usort($ranges, function ($a, $b) {
            if ($a['van'] === null && $b['van'] === null) {
                return 0;
            }
            if ($a['van'] === null) {
                return -1;
            }
            if ($b['van'] === null) {
                return 1;
            }

            return $a['van'] <=> $b['van'];
        });

        $merged = [];

        foreach ($ranges as $range) {
            if (empty($merged)) {
                $merged[] = $range;
                continue;
            }

            $last = &$merged[count($merged) - 1];

            // Consecutive: previous range ends exactly where this one starts
            if ($last['tot'] !== null && $last['tot'] === $range['van']) {
                $last['tot'] = $range['tot'];
            } else {
                $merged[] = $range;
            }
        }

        return $merged;
    }

    // ──────────────────────────────────────────────
    //  Instrumenten (all instruments → overlapping onderdelen)
    // ──────────────────────────────────────────────

    private function importInstrumenten(Relatie $relatie, array $member, Collection $onderdelen): void
    {
        $instruments = collect($member['instrument'] ?? []);
        $earliestLidVan = collect($member['lidmaatschap'] ?? [])
            ->map(fn ($l) => $this->parseDate($l['van']))
            ->filter()
            ->sort()
            ->first();

        // 1. Process ALL instrument records that map to types (including historical)
        $this->importTypesFromInstruments($relatie, $instruments, $earliestLidVan);

        // 2. Pre-process instrument records: parse dates + resolve soorten
        $parsedInstruments = [];
        foreach ($instruments as $record) {
            $raw = strtolower(trim($record['instrument'] ?? ''));

            if (! $raw || in_array($raw, self::SKIP_INSTRUMENTS) || isset(self::TYPE_MAP[$raw])) {
                continue;
            }

            $mapped = self::INSTRUMENT_MAP[$raw] ?? null;

            if ($mapped === null) {
                $this->warn("  Unmapped instrument: '{$raw}' (lid_id {$member['lid_id']})");
                $soorten = [trim($record['instrument'])];
            } else {
                $soorten = is_array($mapped) ? $mapped : [$mapped];
            }

            $parsedInstruments[] = [
                'van' => $this->parseDate($record['van']),
                'tot' => $this->parseDate($record['tot']),
                'soorten' => $soorten,
            ];
        }

        // 3. Per onderdeel pivot, assign only the LATEST overlapping instrument.
        $onderdeelPivots = DB::table('soli_relatie_onderdeel')
            ->where('relatie_id', $relatie->id)
            ->get(['onderdeel_id', 'van', 'tot']);

        // Track assigned [onderdeel_id, instrument_soort] pairs to avoid duplicates
        $assigned = [];

        foreach ($onderdeelPivots as $pivot) {
            $oVan = $pivot->van;
            $oTot = $pivot->tot;

            // Find all instruments that overlap with this onderdeel period
            $overlapping = [];
            foreach ($parsedInstruments as $inst) {
                $instEndedBeforeOnderdeel = $inst['tot'] && $oVan && $inst['tot'] < $oVan;
                $onderdeelEndedBeforeInst = $oTot && $inst['van'] && $oTot < $inst['van'];

                if ($instEndedBeforeOnderdeel || $onderdeelEndedBeforeInst) {
                    continue;
                }

                $overlapping[] = $inst;
            }

            if (empty($overlapping)) {
                continue;
            }

            // Pick the instrument with the latest 'van' date (most recently assigned)
            usort($overlapping, function ($a, $b) {
                if ($a['van'] === null && $b['van'] === null) {
                    return 0;
                }
                if ($a['van'] === null) {
                    return -1;
                }
                if ($b['van'] === null) {
                    return 1;
                }

                return $a['van'] <=> $b['van'];
            });

            $latest = end($overlapping);

            foreach ($latest['soorten'] as $soort) {
                $instrumentSoort = $this->instrumentSoortLookup->get($soort);
                if (! $instrumentSoort) {
                    // Auto-create unknown instrument soorts with self-family fallback
                    $familie = \App\Models\InstrumentFamilie::firstOrCreate(['naam' => $soort]);
                    $instrumentSoort = InstrumentSoort::create(['naam' => $soort, 'instrument_familie_id' => $familie->id]);
                    $this->instrumentSoortLookup->put($soort, $instrumentSoort);
                }

                $key = $pivot->onderdeel_id.':'.$instrumentSoort->id;
                if (isset($assigned[$key])) {
                    continue;
                }

                RelatieInstrument::firstOrCreate([
                    'relatie_id' => $relatie->id,
                    'onderdeel_id' => $pivot->onderdeel_id,
                    'instrument_soort_id' => $instrumentSoort->id,
                ]);
                $assigned[$key] = true;
            }
        }
    }

    private function importTypesFromInstruments(Relatie $relatie, Collection $instruments, ?string $earliestLidVan): void
    {
        // Collect per-type ranges from all instrument records
        $typeRanges = [];

        foreach ($instruments as $record) {
            $raw = strtolower(trim($record['instrument'] ?? ''));
            if (! isset(self::TYPE_MAP[$raw])) {
                continue;
            }

            $typeName = self::TYPE_MAP[$raw];
            $van = $this->parseDate($record['van']);
            $tot = $this->parseDate($record['tot']);

            $typeRanges[$typeName][] = ['van' => $van, 'tot' => $tot];
        }

        // Merge consecutive ranges per type and insert
        foreach ($typeRanges as $typeName => $ranges) {
            $type = RelatieType::where('naam', $typeName)->first();
            if (! $type) {
                continue;
            }

            $merged = $this->mergeRanges($ranges);

            foreach ($merged as $range) {
                $van = $range['van'] ?? $earliestLidVan;
                if (! $van) {
                    continue;
                }

                $exists = DB::table('soli_relatie_relatie_type')
                    ->where('relatie_id', $relatie->id)
                    ->where('relatie_type_id', $type->id)
                    ->where('van', $van)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $relatie->types()->attach($type->id, [
                    'van' => $van,
                    'tot' => $range['tot'],
                ]);
            }
        }
    }

    // ──────────────────────────────────────────────
    //  RelatieType ("lid")
    // ──────────────────────────────────────────────

    private function attachLidType(Relatie $relatie, array $member, RelatieType $lidType): void
    {
        $periods = collect($member['lidmaatschap'] ?? []);

        $van = $periods
            ->map(fn ($l) => $this->parseDate($l['van']))
            ->filter()
            ->sort()
            ->first();

        if (! $van) {
            return;
        }

        $hasOpenPeriod = $periods->contains(fn ($l) => $l['tot'] === null);
        $tot = $hasOpenPeriod
            ? null
            : $periods->map(fn ($l) => $this->parseDate($l['tot']))->filter()->sortDesc()->first();

        $exists = DB::table('soli_relatie_relatie_type')
            ->where('relatie_id', $relatie->id)
            ->where('relatie_type_id', $lidType->id)
            ->exists();

        if ($exists) {
            return;
        }

        $relatie->types()->attach($lidType->id, [
            'van' => $van,
            'tot' => $tot,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    private function parseDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            $parsed = Carbon::createFromFormat('d-m-Y', $date);

            // Reject dates with nonsensical years (e.g. "00-00-0000")
            if ($parsed->year < 1900 || $parsed->year > 2100) {
                return null;
            }

            return $parsed->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
