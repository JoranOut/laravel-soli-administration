<?php

namespace App\Services;

use App\Models\RelatieTypeRoleMapping;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Keeps internal Spatie roles in sync with the relatie types a user holds.
 *
 * Only roles that appear as a target in soli_relatie_type_role_mappings are
 * touched. Every other role assignment stays manual, so a sync can never take
 * away a hand-granted admin or ledenadministratie.
 */
class DerivedRoleSyncService
{
    /**
     * Roles that can never be derived, no matter what the mapping table says.
     *
     * admin is the escape hatch and ledenadministratie is granted on trust, so
     * both stay hand-granted. muziekbeheer is a real role in production even
     * though nothing in this repo creates it — it was dropped from this list
     * once on the strength of a grep, which would have let the sync revoke it
     * from everyone holding it. Verify against the database, not the seeder.
     */
    public const NEVER_MANAGED = ['admin', 'ledenadministratie', 'muziekbeheer'];

    /**
     * Role names this service is allowed to grant and revoke.
     *
     * Every role except NEVER_MANAGED, deliberately *not* only the ones a
     * mapping currently points at: a role has to stay revocable after its
     * mapping is removed, which is the moment you most want it taken away.
     * NEVER_MANAGED is therefore the single knob — those roles are the only
     * ones /admin/users hands out, everything else follows from the types.
     *
     * @return string[]
     */
    public function managedRoleNames(): array
    {
        return Role::whereNotIn('name', self::NEVER_MANAGED)
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Role names the user currently earns through active relatie types.
     *
     * @return string[]
     */
    public function derivedRoleNames(User $user): array
    {
        $activeTypeIds = $this->activeRelatieTypeIds($user);

        if ($activeTypeIds->isEmpty()) {
            return [];
        }

        return RelatieTypeRoleMapping::with('role:id,name')
            ->whereIn('relatie_type_id', $activeTypeIds)
            ->get()
            ->pluck('role.name')
            ->filter()
            ->unique()
            ->reject(fn (string $name) => in_array($name, self::NEVER_MANAGED, true))
            ->values()
            ->all();
    }

    /**
     * What a sync would change for this user, without writing anything.
     *
     * @return array{added: string[], removed: string[]}
     */
    public function diffFor(User $user): array
    {
        return $this->diff(
            $this->managedRoleNames(),
            $this->derivedRoleNames($user),
            $user->roles->pluck('name')->all(),
        );
    }

    /**
     * @param  string[]  $managed  roles this service may touch
     * @param  string[]  $earned  roles the user's active types map to
     * @param  string[]  $held  roles the user has right now
     * @return array{added: string[], removed: string[]}
     */
    private function diff(array $managed, array $earned, array $held): array
    {
        if ($managed === []) {
            return ['added' => [], 'removed' => []];
        }

        return [
            'added' => array_values(array_diff($earned, $held)),
            'removed' => array_values(array_intersect(array_diff($managed, $earned), $held)),
        ];
    }

    /**
     * Derived role names per user id, for every user that earns at least one.
     *
     * One query, so a listing page does not run this per row.
     *
     * @return array<int, string[]>
     */
    public function derivedRolesForAllUsers(): array
    {
        $never = self::NEVER_MANAGED;
        $today = Carbon::today()->toDateString();

        $rows = DB::table('soli_relatie_relatie_type as pivot')
            ->join('soli_relaties as relatie', 'relatie.id', '=', 'pivot.relatie_id')
            ->join('soli_relatie_type_role_mappings as mapping', 'mapping.relatie_type_id', '=', 'pivot.relatie_type_id')
            ->join(config('permission.table_names.roles').' as role', 'role.id', '=', 'mapping.role_id')
            ->whereNotNull('relatie.user_id')
            ->where('relatie.actief', true)
            ->whereNull('relatie.deleted_at')
            ->where('pivot.van', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('pivot.tot')
                    ->orWhere('pivot.tot', '>=', $today);
            })
            ->whereNotIn('role.name', $never)
            ->get(['relatie.user_id', 'role.name']);

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row->user_id][$row->name] = true;
        }

        return array_map(fn (array $names) => array_keys($names), $result);
    }

    /**
     * Bring one user's managed roles in line with their relatie types.
     *
     * @return array{added: string[], removed: string[]}
     */
    public function syncUser(User $user): array
    {
        return $this->apply($user, $this->diffFor($user));
    }

    /**
     * @param  array{added: string[], removed: string[]}  $diff
     * @return array{added: string[], removed: string[]}
     */
    private function apply(User $user, array $diff): array
    {
        if ($diff['added'] === [] && $diff['removed'] === []) {
            return $diff;
        }

        foreach ($diff['added'] as $role) {
            $user->assignRole($role);
        }

        foreach ($diff['removed'] as $role) {
            $user->removeRole($role);
        }

        $user->unsetRelation('roles');

        activity()
            ->performedOn($user)
            ->withProperties($diff)
            ->log("Derived roles synced for {$user->name}");

        return $diff;
    }

    /**
     * Sync every user. Returns the number of users whose roles changed (or
     * would change, under $dryRun).
     */
    public function syncAll(?callable $onChange = null, bool $dryRun = false): int
    {
        // Resolved once for the whole run: doing it per user was three queries
        // and an activity-log insert per account, for every account.
        $managed = $this->managedRoleNames();
        $earnedPerUser = $this->derivedRolesForAllUsers();

        $changed = 0;

        User::with('roles')->chunkById(200, function ($users) use (&$changed, $onChange, $dryRun, $managed, $earnedPerUser) {
            foreach ($users as $user) {
                $diff = $this->diff(
                    $managed,
                    $earnedPerUser[$user->id] ?? [],
                    $user->roles->pluck('name')->all(),
                );

                if ($diff['added'] === [] && $diff['removed'] === []) {
                    continue;
                }

                if (! $dryRun) {
                    $this->apply($user, $diff);
                }

                $changed++;
                $onChange && $onChange($user, $diff);
            }
        });

        return $changed;
    }

    /**
     * Active relatie type ids across all active relaties linked to the user.
     *
     * Mirrors the date window in ClientRoleResolver: van has started and tot is
     * either open or not yet passed.
     */
    private function activeRelatieTypeIds(User $user)
    {
        $today = Carbon::today()->toDateString();

        return DB::table('soli_relatie_relatie_type as pivot')
            ->join('soli_relaties as relatie', 'relatie.id', '=', 'pivot.relatie_id')
            ->where('relatie.user_id', $user->id)
            ->where('relatie.actief', true)
            ->whereNull('relatie.deleted_at')
            ->where('pivot.van', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->whereNull('pivot.tot')
                    ->orWhere('pivot.tot', '>=', $today);
            })
            ->pluck('pivot.relatie_type_id')
            ->unique();
    }
}
