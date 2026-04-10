<?php

namespace App\Services\Google;

use App\Models\GoogleContactGroup;
use App\Models\GoogleContactSync;
use App\Models\Onderdeel;
use App\Models\Relatie;
use Illuminate\Support\Facades\Log;

class GoogleContactSyncService
{
    private const CONTACT_GROUP_TYPES = ['orkest', 'ensemble', 'opleidingsgroep'];

    private const GROUP_PREFIX = 'Soli - ';

    public function __construct(
        private GooglePeopleApiClient $apiClient,
    ) {}

    public function syncAll(?bool $dryRun = false): array
    {
        $users = $this->apiClient->getWorkspaceUsers();
        $summary = ['users' => count($users), 'created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0];

        foreach ($users as $email) {
            $result = $this->syncForUser($email, $dryRun);
            $summary['created'] += $result['created'];
            $summary['updated'] += $result['updated'];
            $summary['deleted'] += $result['deleted'];
            $summary['skipped'] += $result['skipped'];
        }

        return $summary;
    }

    public function syncRelatie(Relatie $relatie, ?bool $dryRun = false): array
    {
        $users = $this->apiClient->getWorkspaceUsers();
        $summary = ['users' => count($users), 'created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0];

        $relatie->load(['emails', 'onderdelen']);

        foreach ($users as $email) {
            $result = $this->syncRelatieForUser($relatie, $email, $dryRun);
            $summary['created'] += $result['created'];
            $summary['updated'] += $result['updated'];
            $summary['deleted'] += $result['deleted'];
            $summary['skipped'] += $result['skipped'];
        }

        return $summary;
    }

    private const BATCH_CREATE_LIMIT = 200;

    private const BATCH_UPDATE_LIMIT = 200;

    private const BATCH_DELETE_LIMIT = 500;

    public function syncForUser(string $googleEmail, ?bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0];

        $service = $this->apiClient->forUser($googleEmail);

        // 1. Ensure contact groups exist
        $groupMap = $this->ensureContactGroups($service, $googleEmail, $dryRun);

        // 2. Load all active relaties with their emails and onderdelen
        $relaties = Relatie::actief()
            ->with(['emails', 'onderdelen' => fn ($q) => $q->actief()])
            ->get();

        $activeRelatieIds = $relaties->pluck('id')->all();

        // 3. Load existing sync mappings for this user
        $existingSyncs = GoogleContactSync::where('google_user_email', $googleEmail)
            ->get()
            ->keyBy('relatie_id');

        // 4. Collect operations
        $toCreate = [];  // [{relatie, person, hash}, ...]
        $toUpdate = [];  // [{relatie, person, hash, sync, existingPerson}, ...]
        $toRecreate = []; // [{relatie, person, hash, sync}, ...]

        foreach ($relaties as $relatie) {
            $hash = $this->computeDataHash($relatie);
            $existing = $existingSyncs->get($relatie->id);

            if ($existing && $existing->data_hash === $hash) {
                $stats['skipped']++;

                continue;
            }

            $groupResourceNames = $this->resolveGroupResourceNames($relatie, $groupMap);
            $person = $this->apiClient->buildPerson($relatie, $groupResourceNames);

            if ($dryRun) {
                $stats[$existing ? 'updated' : 'created']++;

                continue;
            }

            if ($existing) {
                // Check if contact still exists in Google
                try {
                    $existingPerson = $this->apiClient->getContact($service, $existing->google_resource_name);

                    if (! $existingPerson) {
                        $toRecreate[] = ['relatie' => $relatie, 'person' => $person, 'hash' => $hash, 'sync' => $existing];
                    } else {
                        $etag = $this->apiClient->getEtag($existingPerson);
                        $person->setEtag($etag);
                        $toUpdate[] = ['relatie' => $relatie, 'person' => $person, 'hash' => $hash, 'sync' => $existing];
                    }
                } catch (\Throwable $e) {
                    Log::warning('Google Contacts sync getContact failed', [
                        'relatie_id' => $relatie->id,
                        'google_user' => $googleEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            } else {
                $toCreate[] = ['relatie' => $relatie, 'person' => $person, 'hash' => $hash];
            }
        }

        // 5. Execute batch creates (including re-creates)
        $allCreates = array_merge($toCreate, $toRecreate);

        foreach (array_chunk($allCreates, self::BATCH_CREATE_LIMIT) as $chunk) {
            try {
                $persons = array_map(fn ($item) => $item['person'], $chunk);
                $created = $this->apiClient->batchCreateContacts($service, $persons);

                foreach ($created as $index => $createdPerson) {
                    $item = $chunk[$index];
                    $resourceName = $createdPerson->getResourceName();

                    if (isset($item['sync'])) {
                        // Re-create: update existing mapping
                        $item['sync']->update([
                            'google_resource_name' => $resourceName,
                            'data_hash' => $item['hash'],
                        ]);
                    } else {
                        // New create
                        GoogleContactSync::create([
                            'relatie_id' => $item['relatie']->id,
                            'google_user_email' => $googleEmail,
                            'google_resource_name' => $resourceName,
                            'data_hash' => $item['hash'],
                        ]);
                    }
                    $stats['created']++;
                }
            } catch (\Throwable $e) {
                Log::warning('Google Contacts batch create failed', [
                    'google_user' => $googleEmail,
                    'count' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 6. Execute batch updates
        foreach (array_chunk($toUpdate, self::BATCH_UPDATE_LIMIT) as $chunk) {
            try {
                $persons = [];
                foreach ($chunk as $item) {
                    $persons[$item['sync']->google_resource_name] = $item['person'];
                }
                $this->apiClient->batchUpdateContacts($service, $persons);

                foreach ($chunk as $item) {
                    $item['sync']->update(['data_hash' => $item['hash']]);
                    $stats['updated']++;
                }
            } catch (\Throwable $e) {
                Log::warning('Google Contacts batch update failed', [
                    'google_user' => $googleEmail,
                    'count' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 7. Delete contacts for relaties no longer active
        $toDelete = $existingSyncs->filter(fn ($sync) => ! in_array($sync->relatie_id, $activeRelatieIds));

        if ($dryRun) {
            $stats['deleted'] += $toDelete->count();
        } else {
            foreach ($toDelete->chunk(self::BATCH_DELETE_LIMIT) as $chunk) {
                $resourceNames = $chunk->pluck('google_resource_name')->all();

                try {
                    $this->apiClient->batchDeleteContacts($service, $resourceNames);
                } catch (\Throwable $e) {
                    Log::warning('Google Contacts batch delete failed', [
                        'google_user' => $googleEmail,
                        'count' => count($resourceNames),
                        'error' => $e->getMessage(),
                    ]);
                }

                foreach ($chunk as $sync) {
                    $sync->delete();
                    $stats['deleted']++;
                }
            }
        }

        // 8. Clean up groups for inactive onderdelen
        if (! $dryRun) {
            $this->cleanupStaleGroups($service, $googleEmail);
        }

        return $stats;
    }

    private function resolveGroupResourceNames(Relatie $relatie, array $groupMap): array
    {
        $activeOnderdeelIds = $relatie->onderdelen
            ->filter(fn ($o) => $o->pivot->tot === null || $o->pivot->tot >= now()->toDateString())
            ->pluck('id')
            ->all();

        return collect($activeOnderdeelIds)
            ->map(fn ($id) => $groupMap[$id] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    public function syncRelatieForUser(Relatie $relatie, string $googleEmail, ?bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0];

        $service = $this->apiClient->forUser($googleEmail);

        // Ensure contact groups exist
        $groupMap = $this->ensureContactGroups($service, $googleEmail, $dryRun);

        $hash = $this->computeDataHash($relatie);
        $existing = GoogleContactSync::where('relatie_id', $relatie->id)
            ->where('google_user_email', $googleEmail)
            ->first();

        // If relatie is no longer active, delete
        if (! $relatie->actief) {
            if ($existing) {
                if (! $dryRun) {
                    try {
                        $this->apiClient->deleteContact($service, $existing->google_resource_name);
                    } catch (\Google\Service\Exception $e) {
                        if ($e->getCode() !== 404) {
                            throw $e;
                        }
                    }
                    $existing->delete();
                }
                $stats['deleted']++;
            }

            return $stats;
        }

        if ($existing && $existing->data_hash === $hash) {
            $stats['skipped']++;

            return $stats;
        }

        // Resolve group resource names
        $activeOnderdeelIds = $relatie->onderdelen
            ->filter(fn ($o) => $o->pivot->tot === null || $o->pivot->tot >= now()->toDateString())
            ->pluck('id')
            ->all();

        $groupResourceNames = collect($activeOnderdeelIds)
            ->map(fn ($id) => $groupMap[$id] ?? null)
            ->filter()
            ->values()
            ->all();

        $person = $this->apiClient->buildPerson($relatie, $groupResourceNames);

        if ($dryRun) {
            $stats[$existing ? 'updated' : 'created']++;

            return $stats;
        }

        if ($existing) {
            try {
                $existingPerson = $this->apiClient->getContact($service, $existing->google_resource_name);

                if (! $existingPerson) {
                    $created = $this->apiClient->createContact($service, $person);
                    $existing->update([
                        'google_resource_name' => $created->getResourceName(),
                        'data_hash' => $hash,
                    ]);
                    $stats['created']++;

                    return $stats;
                }

                $etag = $this->apiClient->getEtag($existingPerson);
                $this->apiClient->updateContact($service, $existing->google_resource_name, $person, $etag);
                $existing->update(['data_hash' => $hash]);
                $stats['updated']++;
            } catch (\Throwable $e) {
                Log::warning('Google Contacts sync update failed', [
                    'relatie_id' => $relatie->id,
                    'google_user' => $googleEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            try {
                $created = $this->apiClient->createContact($service, $person);
                GoogleContactSync::create([
                    'relatie_id' => $relatie->id,
                    'google_user_email' => $googleEmail,
                    'google_resource_name' => $created->getResourceName(),
                    'data_hash' => $hash,
                ]);
                $stats['created']++;
            } catch (\Throwable $e) {
                Log::warning('Google Contacts sync create failed', [
                    'relatie_id' => $relatie->id,
                    'google_user' => $googleEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    public function computeDataHash(Relatie $relatie): string
    {
        $data = [
            'voornaam' => $relatie->voornaam,
            'tussenvoegsel' => $relatie->tussenvoegsel,
            'achternaam' => $relatie->achternaam,
            'actief' => $relatie->actief,
            'emails' => $relatie->emails->pluck('email')->sort()->values()->all(),
            'onderdeel_ids' => $relatie->onderdelen
                ->filter(fn ($o) => $o->pivot->tot === null || $o->pivot->tot >= now()->toDateString())
                ->pluck('id')
                ->sort()
                ->values()
                ->all(),
        ];

        return hash('sha256', json_encode($data));
    }

    private function ensureContactGroups(mixed $service, string $googleEmail, bool $dryRun): array
    {
        $onderdelen = Onderdeel::actief()
            ->whereIn('type', self::CONTACT_GROUP_TYPES)
            ->get();

        $existingGroups = GoogleContactGroup::where('google_user_email', $googleEmail)
            ->get()
            ->keyBy('onderdeel_id');

        $existingApiGroups = null;
        $groupMap = [];

        foreach ($onderdelen as $onderdeel) {
            $existing = $existingGroups->get($onderdeel->id);

            if ($existing) {
                $groupMap[$onderdeel->id] = $existing->google_resource_name;

                continue;
            }

            if ($dryRun) {
                continue;
            }

            // Lazy-load API groups to check if the group already exists
            if ($existingApiGroups === null) {
                $existingApiGroups = collect($this->apiClient->listContactGroups($service))
                    ->keyBy(fn ($g) => $g->getName());
            }

            $groupName = self::GROUP_PREFIX.$onderdeel->naam;
            $apiGroup = $existingApiGroups->get($groupName);

            if ($apiGroup) {
                $resourceName = $apiGroup->getResourceName();
            } else {
                $created = $this->apiClient->createContactGroup($service, $groupName);
                $resourceName = $created->getResourceName();
            }

            GoogleContactGroup::create([
                'onderdeel_id' => $onderdeel->id,
                'google_user_email' => $googleEmail,
                'google_resource_name' => $resourceName,
            ]);

            $groupMap[$onderdeel->id] = $resourceName;
        }

        return $groupMap;
    }

    private function cleanupStaleGroups(mixed $service, string $googleEmail): void
    {
        $activeOnderdeelIds = Onderdeel::actief()
            ->whereIn('type', self::CONTACT_GROUP_TYPES)
            ->pluck('id');

        $staleGroups = GoogleContactGroup::where('google_user_email', $googleEmail)
            ->whereNotIn('onderdeel_id', $activeOnderdeelIds)
            ->get();

        foreach ($staleGroups as $group) {
            try {
                $this->apiClient->deleteContactGroup($service, $group->google_resource_name);
            } catch (\Google\Service\Exception $e) {
                if ($e->getCode() !== 404) {
                    Log::warning('Google Contacts group cleanup failed', [
                        'onderdeel_id' => $group->onderdeel_id,
                        'google_user' => $googleEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $group->delete();
        }
    }
}
