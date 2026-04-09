<?php

namespace App\Services;

use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MemberSyncService
{
    public const STATUS_CREATED = 'created';

    public const STATUS_UPDATED = 'updated';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_ALREADY_INACTIVE = 'already_inactive';

    public const STATUS_DEACTIVATED = 'deactivated';

    public const STATUS_RECONCILED = 'reconciled';

    private ?array $onderdeelMap = null;

    public function upsertMember(int $lidId, array $data): array
    {
        return DB::transaction(function () use ($lidId, $data) {
            $relatie = Relatie::withTrashed()->lockForUpdate()->where('relatie_nummer', $lidId)->first();

            if ($relatie) {
                return $this->updateMember($relatie, $data);
            }

            return $this->createMember($lidId, $data);
        });
    }

    public function deactivateMember(int $lidId): array
    {
        return DB::transaction(function () use ($lidId) {
            $relatie = Relatie::withTrashed()->lockForUpdate()->where('relatie_nummer', $lidId)->first();

            if (! $relatie) {
                return ['status' => self::STATUS_NOT_FOUND];
            }

            if (! $relatie->actief && ! $relatie->trashed()) {
                return [
                    'status' => self::STATUS_ALREADY_INACTIVE,
                    'relatie_id' => $relatie->id,
                ];
            }

            if ($relatie->trashed()) {
                $relatie->restore();
            }

            $this->performDeactivation($relatie);

            return [
                'status' => self::STATUS_DEACTIVATED,
                'relatie_id' => $relatie->id,
            ];
        });
    }

    public function reconcileMembers(array $activeLidIds): array
    {
        return DB::transaction(function () use ($activeLidIds) {
            $totalActive = Relatie::where('actief', true)->lockForUpdate()->count();
            $relatiesToDeactivate = Relatie::where('actief', true)
                ->whereNotIn('relatie_nummer', $activeLidIds)
                ->lockForUpdate()
                ->get();

            if ($totalActive > 0 && ($relatiesToDeactivate->count() / $totalActive) > 0.2) {
                throw new \RuntimeException(
                    "Reconcile aborted: would deactivate {$relatiesToDeactivate->count()} of {$totalActive} active members (exceeds 20% threshold)."
                );
            }

            $deactivated = [];

            foreach ($relatiesToDeactivate as $relatie) {
                $this->performDeactivation($relatie);
                $deactivated[] = $relatie->relatie_nummer;
            }

            return [
                'status' => self::STATUS_RECONCILED,
                'deactivated' => $deactivated,
                'deactivated_count' => count($deactivated),
            ];
        });
    }

    private function createMember(int $lidId, array $data): array
    {
        $warnings = [];

        $relatie = Relatie::create([
            'relatie_nummer' => $lidId,
            'voornaam' => $data['voornaam'],
            'tussenvoegsel' => $data['tussenvoegsel'] ?? null,
            'achternaam' => $data['achternaam'],
            'geslacht' => 'O',
            'actief' => true,
        ]);

        // Create email record
        $relatie->emails()->create(['email' => $data['email']]);

        // Create or link user account
        $this->ensureUserAccount($relatie, $data);

        // Attach "lid" type
        $lidType = $this->getLidType();
        if ($lidType) {
            $relatie->types()->attach($lidType->id, [
                'van' => now()->toDateString(),
            ]);
        } else {
            $warnings[] = 'RelatieType "lid" not found; skipped type assignment.';
        }

        // Sync onderdelen
        $onderdeelResult = $this->syncOnderdelen($relatie, $data['onderdeel_codes'] ?? []);
        $warnings = array_merge($warnings, $onderdeelResult['warnings']);

        return [
            'status' => self::STATUS_CREATED,
            'relatie_id' => $relatie->id,
            'warnings' => $warnings,
        ];
    }

    private function updateMember(Relatie $relatie, array $data): array
    {
        $warnings = [];

        // Restore if soft-deleted
        if ($relatie->trashed()) {
            $relatie->restore();
        }

        // Update name fields and ensure active
        $relatie->update([
            'voornaam' => $data['voornaam'],
            'tussenvoegsel' => $data['tussenvoegsel'] ?? null,
            'achternaam' => $data['achternaam'],
            'actief' => true,
        ]);

        // Ensure email exists on relatie
        $existingEmail = $relatie->emails()->where('email', $data['email'])->first();
        if (! $existingEmail) {
            $relatie->emails()->create(['email' => $data['email']]);
        }

        // Ensure user account exists and email is in sync
        if (! $relatie->user_id) {
            $this->ensureUserAccount($relatie, $data);
        } else {
            $this->syncUserEmail($relatie, $data['email']);
        }

        // Ensure "lid" type is active
        $lidType = $this->getLidType();
        if ($lidType) {
            $hasActiveLidType = $relatie->types()
                ->where('soli_relatie_relatie_type.relatie_type_id', $lidType->id)
                ->where(function ($q) {
                    $q->whereNull('soli_relatie_relatie_type.tot')
                        ->orWhere('soli_relatie_relatie_type.tot', '>=', now()->toDateString());
                })
                ->exists();

            if (! $hasActiveLidType) {
                $relatie->types()->attach($lidType->id, [
                    'van' => now()->toDateString(),
                ]);
            }
        } else {
            $warnings[] = 'RelatieType "lid" not found; skipped type assignment.';
        }

        // Sync onderdelen
        $onderdeelResult = $this->syncOnderdelen($relatie, $data['onderdeel_codes'] ?? []);
        $warnings = array_merge($warnings, $onderdeelResult['warnings']);

        return [
            'status' => self::STATUS_UPDATED,
            'relatie_id' => $relatie->id,
            'warnings' => $warnings,
        ];
    }

    private function performDeactivation(Relatie $relatie): void
    {
        $relatie->update(['actief' => false]);

        // Delete linked user account
        if ($relatie->user_id) {
            $relatie->user()->delete();
            $relatie->user_id = null;
            $relatie->save();
        }

        // Close active "lid" type assignment
        $lidType = $this->getLidType();
        if ($lidType) {
            $relatie->types()
                ->wherePivot('relatie_type_id', $lidType->id)
                ->wherePivotNull('tot')
                ->update(['soli_relatie_relatie_type.tot' => now()->toDateString()]);
        }

        // Close active onderdeel assignments
        $relatie->onderdelen()
            ->wherePivotNull('tot')
            ->update(['soli_relatie_onderdeel.tot' => now()->toDateString()]);
    }

    private function ensureUserAccount(Relatie $relatie, array $data): void
    {
        try {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => collect([$data['voornaam'], $data['tussenvoegsel'] ?? null, $data['achternaam']])
                        ->filter()->implode(' '),
                    'password' => Str::random(32),
                ]
            );
        } catch (UniqueConstraintViolationException) {
            // Concurrent request created the user between check and insert
            $user = User::where('email', $data['email'])->first();
        }

        if (! $user) {
            throw new \RuntimeException("Failed to create or find user for email: {$data['email']}");
        }

        if (! $user->hasRole('member')) {
            $user->assignRole('member');
        }

        $relatie->user_id = $user->id;
        $relatie->save();
    }

    private function syncUserEmail(Relatie $relatie, string $newEmail): void
    {
        $user = $relatie->user;

        if (! $user || $user->email === $newEmail) {
            return;
        }

        // Only update if no other user already has this email
        if (User::where('email', $newEmail)->where('id', '!=', $user->id)->exists()) {
            Log::warning("MemberSyncService: Cannot update user {$user->id} email to {$newEmail} — already taken by another user.");

            return;
        }

        $user->email = $newEmail;
        $user->email_verified_at = null;
        $user->save();
    }

    private function getLidType(): ?RelatieType
    {
        $lidType = RelatieType::where('naam', 'lid')->first();

        if (! $lidType) {
            Log::warning('MemberSyncService: RelatieType "lid" not found in database.');
        }

        return $lidType;
    }

    private function syncOnderdelen(Relatie $relatie, array $codes): array
    {
        $warnings = [];
        $codeMap = $this->getOnderdeelMap();

        // Resolve desired onderdeel IDs from codes
        $desiredIds = [];
        foreach ($codes as $code) {
            if (isset($codeMap[$code])) {
                $desiredIds[] = $codeMap[$code];
            } else {
                $warnings[] = "Unknown onderdeel code: {$code}";
            }
        }

        // Get current active onderdeel assignments
        $activeAssignments = $relatie->onderdelen()
            ->wherePivotNull('tot')
            ->get();

        $currentIds = $activeAssignments->pluck('id')->toArray();

        // Close assignments not in desired list
        $toClose = array_diff($currentIds, $desiredIds);
        if (! empty($toClose)) {
            $relatie->onderdelen()
                ->wherePivotNull('tot')
                ->wherePivotIn('onderdeel_id', $toClose)
                ->update(['soli_relatie_onderdeel.tot' => now()->toDateString()]);
        }

        // Add new assignments
        $toAdd = array_diff($desiredIds, $currentIds);
        foreach ($toAdd as $onderdeelId) {
            $relatie->onderdelen()->attach($onderdeelId, [
                'van' => now()->toDateString(),
            ]);
        }

        return [
            'added' => count($toAdd),
            'closed' => count($toClose),
            'warnings' => $warnings,
        ];
    }

    private function getOnderdeelMap(): array
    {
        if ($this->onderdeelMap === null) {
            $this->onderdeelMap = Onderdeel::whereNotNull('afkorting')
                ->pluck('id', 'afkorting')
                ->toArray();
        }

        return $this->onderdeelMap;
    }
}
