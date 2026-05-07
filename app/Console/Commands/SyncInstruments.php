<?php

namespace App\Console\Commands;

use App\Models\InstrumentFamilie;
use App\Models\InstrumentSoort;
use App\Services\MusicLibraryApiService;
use Illuminate\Console\Command;
use Throwable;

class SyncInstruments extends Command
{
    protected $signature = 'sync:instruments';

    protected $description = 'Sync instrument families and types from the music library';

    public function handle(MusicLibraryApiService $api): int
    {
        try {
            $data = $api->getInstruments();
        } catch (Throwable $e) {
            $this->error("Instrument sync failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        $familyIdMap = $this->syncFamilies($data['families']);
        $typeCount = $this->syncSoorten($data['soorten'], $familyIdMap);

        $this->info("Synced ".count($data['families'])." families, {$typeCount} instrument types");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{id: int, name: string}>  $families
     * @return array<int, int> Remote ID → local ID mapping
     */
    private function syncFamilies(array $families): array
    {
        $map = [];

        foreach ($families as $family) {
            $local = InstrumentFamilie::updateOrCreate(
                ['external_id' => $family['id']],
                ['naam' => $family['name']],
            );
            $map[$family['id']] = $local->id;
        }

        return $map;
    }

    /**
     * @param  array<int, array{id: int, name: string, instrument_family_id: int}>  $soorten
     * @param  array<int, int>  $familyIdMap
     */
    private function syncSoorten(array $soorten, array $familyIdMap): int
    {
        $count = 0;

        foreach ($soorten as $soort) {
            $localFamilyId = $familyIdMap[$soort['instrument_family_id']] ?? null;

            if (! $localFamilyId) {
                continue;
            }

            InstrumentSoort::updateOrCreate(
                ['external_id' => $soort['id']],
                ['naam' => $soort['name'], 'instrument_familie_id' => $localFamilyId],
            );
            $count++;
        }

        return $count;
    }
}
