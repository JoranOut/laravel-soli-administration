<?php

namespace App\Services\Google;

use App\Models\GoogleContactGroup;
use App\Models\GoogleContactSyncLog;
use App\Models\GoogleContactTypeGroup;
use App\Models\GoogleContactSync;
use App\Models\JobStatus;
use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieType;
use Illuminate\Support\Facades\Log;

class GoogleContactSyncService
{
    private const CONTACT_GROUP_TYPES = ['muziekgroep'];

    private const GROUP_PREFIX = 'Soli - ';

    public function __construct(
        private GooglePeopleApiClient $apiClient,
    ) {}

    private ?\Closure $output = null;

    public function withOutput(\Closure $output): self
    {
        $this->output = $output;

        return $this;
    }

    private function log(string $message): void
    {
        if ($this->output) {
            ($this->output)($message);
        }
    }

    public function syncAll(?bool $dryRun = false): array
    {
        $log = $dryRun ? null : GoogleContactSyncLog::create([
            'type' => 'full',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $jobStatus = $dryRun ? null : JobStatus::markRunning('google-contacts-sync', 'Google Contacts Sync');

        try {
            $users = $this->apiClient->getWorkspaceUsers();
            $summary = ['users' => count($users), 'created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

            $isFirst = true;
            foreach ($users as $email) {
                $result = $this->syncForUser($email, $dryRun);
                // Count unique relaties (each user processes the same set),
                // so only use the first user's stats as representative.
                if ($isFirst) {
                    $summary['created'] = $result['created'];
                    $summary['updated'] = $result['updated'];
                    $summary['deleted'] = $result['deleted'];
                    $summary['skipped'] = $result['skipped'];
                    $summary['failed'] = $result['failed'];
                    $isFirst = false;
                }
                // Aggregate errors across all users
                if (! empty($result['errors'])) {
                    $summary['errors'] = array_merge($summary['errors'], $result['errors']);
                }
            }

            $hasErrors = ! empty($summary['errors']);
            $logSummary = array_diff_key($summary, ['errors' => true]);

            $log?->update([
                'status' => $hasErrors ? 'completed_with_errors' : 'completed',
                'workspace_users' => $summary['users'],
                'contacts_created' => $summary['created'],
                'contacts_updated' => $summary['updated'],
                'contacts_deleted' => $summary['deleted'],
                'contacts_skipped' => $summary['skipped'],
                'contacts_failed' => $summary['failed'],
                'error_message' => $hasErrors ? implode("\n", $summary['errors']) : null,
                'completed_at' => now(),
            ]);

            if ($hasErrors) {
                $jobStatus?->markCompletedWithErrors(implode("\n", $summary['errors']), $logSummary);
            } else {
                $jobStatus?->markCompleted($logSummary);
            }

            return $summary;
        } catch (\Throwable $e) {
            $log?->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $jobStatus?->markFailed($e->getMessage());

            throw $e;
        }
    }

    public function syncRelatie(Relatie $relatie, ?bool $dryRun = false): array
    {
        $log = $dryRun ? null : GoogleContactSyncLog::create([
            'type' => 'relatie',
            'relatie_id' => $relatie->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $jobStatus = $dryRun ? null : JobStatus::markRunning('google-contacts-sync', 'Google Contacts Sync');

        try {
            $users = $this->apiClient->getWorkspaceUsers();
            $summary = ['users' => count($users), 'created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

            $relatie->load(['emails', 'onderdelen', 'types']);

            foreach ($users as $email) {
                $result = $this->syncRelatieForUser($relatie, $email, $dryRun);
                // Use max so stats reflect the single relatie (0 or 1),
                // not multiplied by the number of workspace users.
                $summary['created'] = max($summary['created'], $result['created']);
                $summary['updated'] = max($summary['updated'], $result['updated']);
                $summary['deleted'] = max($summary['deleted'], $result['deleted']);
                $summary['skipped'] = max($summary['skipped'], $result['skipped']);
                $summary['failed'] = max($summary['failed'], $result['failed']);
                // Aggregate errors across all users
                if (! empty($result['errors'])) {
                    $summary['errors'] = array_merge($summary['errors'], $result['errors']);
                }
            }

            $hasErrors = ! empty($summary['errors']);
            $logSummary = array_diff_key($summary, ['errors' => true]);

            $log?->update([
                'status' => $hasErrors ? 'completed_with_errors' : 'completed',
                'workspace_users' => $summary['users'],
                'contacts_created' => $summary['created'],
                'contacts_updated' => $summary['updated'],
                'contacts_deleted' => $summary['deleted'],
                'contacts_skipped' => $summary['skipped'],
                'contacts_failed' => $summary['failed'],
                'error_message' => $hasErrors ? implode("\n", $summary['errors']) : null,
                'completed_at' => now(),
            ]);

            if ($hasErrors) {
                $jobStatus?->markCompletedWithErrors(implode("\n", $summary['errors']), $logSummary);
            } else {
                $jobStatus?->markCompleted($logSummary);
            }

            return $summary;
        } catch (\Throwable $e) {
            $log?->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            $jobStatus?->markFailed($e->getMessage());

            throw $e;
        }
    }

    private const BATCH_CREATE_LIMIT = 200;

    private const BATCH_UPDATE_LIMIT = 200;

    private const BATCH_DELETE_LIMIT = 500;

    public function syncForUser(string $googleEmail, ?bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        $service = $this->apiClient->forUser($googleEmail);

        // 1. Ensure contact groups exist
        $groupMap = $this->ensureContactGroups($service, $googleEmail, $dryRun);
        $typeGroupMap = $this->ensureContactTypeGroups($service, $googleEmail, $dryRun);

        // 2. Load all active relaties with their emails, onderdelen and types
        $relaties = Relatie::actief()
            ->with(['emails', 'onderdelen' => fn ($q) => $q->actief(), 'types'])
            ->get();

        $this->log("Processing {$relaties->count()} active relaties for {$googleEmail}");

        $activeRelatieIds = $relaties->pluck('id')->all();

        // 3. Load existing sync mappings for this user
        $existingSyncs = GoogleContactSync::where('google_user_email', $googleEmail)
            ->get()
            ->keyBy('relatie_id');

        // 4. Pre-fetch all managed contacts from Google (uses read requests, not critical reads)
        //    This replaces individual getContact() calls which cost 1 critical read each.
        $contactMap = [];
        if (! $dryRun) {
            $managedContacts = $this->apiClient->listManagedContacts($service);
            foreach ($managedContacts as $contact) {
                $contactMap[$contact->getResourceName()] = $contact;
            }
            $this->log("Pre-fetched " . count($contactMap) . " managed contacts from Google for {$googleEmail}");
        }

        // 5. Collect operations
        $toCreate = [];  // [{relatie, person, hash}, ...]
        $toUpdate = [];  // [{relatie, person, hash, sync}, ...]
        $toRecreate = []; // [{relatie, person, hash, sync}, ...]

        foreach ($relaties as $relatie) {
            $hash = $this->computeDataHash($relatie);
            $existing = $existingSyncs->get($relatie->id);

            if ($existing && $existing->data_hash === $hash) {
                $stats['skipped']++;

                continue;
            }

            $groupResourceNames = $this->resolveGroupResourceNames($relatie, $groupMap, $typeGroupMap);

            $person = $this->apiClient->buildPerson($relatie, $groupResourceNames);

            if ($dryRun) {
                $stats[$existing ? 'updated' : 'created']++;

                continue;
            }

            if ($existing) {
                // Check if contact still exists in Google using pre-fetched map
                $existingPerson = $contactMap[$existing->google_resource_name] ?? null;

                if (! $existingPerson) {
                    $toRecreate[] = ['relatie' => $relatie, 'person' => $person, 'hash' => $hash, 'sync' => $existing];
                } else {
                    $etag = $this->apiClient->getEtag($existingPerson);
                    $person->setEtag($etag);
                    $toUpdate[] = ['relatie' => $relatie, 'person' => $person, 'hash' => $hash, 'sync' => $existing];
                }
            } else {
                $toCreate[] = ['relatie' => $relatie, 'person' => $person, 'hash' => $hash];
            }
        }

        $this->log("Collected: " . count($toCreate) . " create, " . count($toUpdate) . " update, " . count($toRecreate) . " recreate, {$stats['skipped']} skipped");

        // 6. Execute batch creates (including re-creates)
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
                $stats['failed'] += count($chunk);
                $stats['errors'][] = "Batch create failed ({$googleEmail}, " . count($chunk) . " contacts): {$e->getMessage()}";
                $this->log("ERROR batch create ({$googleEmail}, " . count($chunk) . " contacts): {$e->getMessage()}");
                Log::warning('Google Contacts batch create failed', [
                    'google_user' => $googleEmail,
                    'count' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 7. Execute batch updates
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
                $stats['failed'] += count($chunk);
                $stats['errors'][] = "Batch update failed ({$googleEmail}, " . count($chunk) . " contacts): {$e->getMessage()}";
                $this->log("ERROR batch update ({$googleEmail}, " . count($chunk) . " contacts): {$e->getMessage()}");
                Log::warning('Google Contacts batch update failed', [
                    'google_user' => $googleEmail,
                    'count' => count($chunk),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 8. Delete contacts for relaties no longer active
        $toDelete = $existingSyncs->filter(fn ($sync) => ! in_array($sync->relatie_id, $activeRelatieIds));

        if ($dryRun) {
            $stats['deleted'] += $toDelete->count();
        } else {
            foreach ($toDelete->chunk(self::BATCH_DELETE_LIMIT) as $chunk) {
                $resourceNames = $chunk->pluck('google_resource_name')->all();

                try {
                    $this->apiClient->batchDeleteContacts($service, $resourceNames);

                    foreach ($chunk as $sync) {
                        $sync->delete();
                        $stats['deleted']++;
                    }
                } catch (\Throwable $e) {
                    $stats['failed'] += count($resourceNames);
                    $stats['errors'][] = "Batch delete failed ({$googleEmail}, " . count($resourceNames) . " contacts): {$e->getMessage()}";
                    $this->log("ERROR batch delete ({$googleEmail}, " . count($resourceNames) . " contacts): {$e->getMessage()}");
                    Log::warning('Google Contacts batch delete failed', [
                        'google_user' => $googleEmail,
                        'count' => count($resourceNames),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // 9. Clean up stale groups
        if (! $dryRun) {
            $this->cleanupStaleGroups($service, $googleEmail);
            $this->cleanupStaleTypeGroups($service, $googleEmail);
        }

        return $stats;
    }

    public function syncRelatieForUser(Relatie $relatie, string $googleEmail, ?bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        $service = $this->apiClient->forUser($googleEmail);

        // Ensure contact groups exist
        $groupMap = $this->ensureContactGroups($service, $googleEmail, $dryRun);
        $typeGroupMap = $this->ensureContactTypeGroups($service, $googleEmail, $dryRun);

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
        $groupResourceNames = $this->resolveGroupResourceNames($relatie, $groupMap, $typeGroupMap);

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
                $stats['failed']++;
                $stats['errors'][] = "Update failed ({$googleEmail}, relatie {$relatie->id}): {$e->getMessage()}";
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
                $stats['failed']++;
                $stats['errors'][] = "Create failed ({$googleEmail}, relatie {$relatie->id}): {$e->getMessage()}";
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
            'type_assignments' => $relatie->types
                ->filter(fn ($t) => $t->pivot->tot === null || $t->pivot->tot >= now()->toDateString())
                ->map(fn ($t) => [$t->id, $t->pivot->onderdeel_id])
                ->sortBy(fn ($pair) => $pair[0])
                ->values()
                ->all(),
        ];

        return hash('sha256', json_encode($data));
    }

    private function resolveGroupResourceNames(Relatie $relatie, array $groupMap, array $typeGroupMap): array
    {
        $onderdeelGroups = $relatie->onderdelen
            ->filter(fn ($o) => $o->pivot->tot === null || $o->pivot->tot >= now()->toDateString())
            ->pluck('id')
            ->map(fn ($id) => $groupMap[$id] ?? null)
            ->filter()
            ->values()
            ->all();

        $typeGroups = $relatie->types
            ->filter(fn ($t) => $t->pivot->tot === null || $t->pivot->tot >= now()->toDateString())
            ->pluck('id')
            ->map(fn ($id) => $typeGroupMap[$id] ?? null)
            ->filter()
            ->values()
            ->all();

        return array_merge($onderdeelGroups, $typeGroups);
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

    private function ensureContactTypeGroups(mixed $service, string $googleEmail, bool $dryRun): array
    {
        $types = RelatieType::all();

        $existingGroups = GoogleContactTypeGroup::where('google_user_email', $googleEmail)
            ->get()
            ->keyBy('relatie_type_id');

        $existingApiGroups = null;
        $typeGroupMap = [];

        foreach ($types as $type) {
            $existing = $existingGroups->get($type->id);

            if ($existing) {
                $typeGroupMap[$type->id] = $existing->google_resource_name;

                continue;
            }

            if ($dryRun) {
                continue;
            }

            if ($existingApiGroups === null) {
                $existingApiGroups = collect($this->apiClient->listContactGroups($service))
                    ->keyBy(fn ($g) => $g->getName());
            }

            $groupName = self::GROUP_PREFIX.ucfirst($type->naam);
            $apiGroup = $existingApiGroups->get($groupName);

            if ($apiGroup) {
                $resourceName = $apiGroup->getResourceName();
            } else {
                $created = $this->apiClient->createContactGroup($service, $groupName);
                $resourceName = $created->getResourceName();
            }

            GoogleContactTypeGroup::create([
                'relatie_type_id' => $type->id,
                'google_user_email' => $googleEmail,
                'google_resource_name' => $resourceName,
            ]);

            $typeGroupMap[$type->id] = $resourceName;
        }

        return $typeGroupMap;
    }

    private function cleanupStaleTypeGroups(mixed $service, string $googleEmail): void
    {
        $activeTypeIds = RelatieType::pluck('id');

        $staleGroups = GoogleContactTypeGroup::where('google_user_email', $googleEmail)
            ->whereNotIn('relatie_type_id', $activeTypeIds)
            ->get();

        foreach ($staleGroups as $group) {
            try {
                $this->apiClient->deleteContactGroup($service, $group->google_resource_name);
            } catch (\Google\Service\Exception $e) {
                if ($e->getCode() !== 404) {
                    Log::warning('Google Contacts type group cleanup failed', [
                        'relatie_type_id' => $group->relatie_type_id,
                        'google_user' => $googleEmail,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $group->delete();
        }
    }
}
