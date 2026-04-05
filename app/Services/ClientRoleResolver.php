<?php

namespace App\Services;

use App\Models\OauthClientSetting;
use App\Models\User;
use Carbon\Carbon;

class ClientRoleResolver
{
    public const NO_ACCESS = '__no_access__';

    /**
     * Resolve mapped roles for a user based on the OAuth client's settings.
     *
     * Falls back to Spatie roles if no client settings exist.
     * Returns an empty array when the resolved role is NO_ACCESS.
     *
     * @return string[]
     */
    public function resolve(User $user, string $clientId): array
    {
        $setting = OauthClientSetting::with('roleMappings')
            ->where('client_id', $clientId)
            ->first();

        if (! $setting) {
            return $user->getRoleNames()->toArray();
        }

        $today = Carbon::today();

        // Collect all active relatie type IDs across all user's relaties
        $activeTypeIds = $user->relaties()
            ->with(['types' => function ($query) use ($today) {
                $query->wherePivot('van', '<=', $today)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('soli_relatie_relatie_type.tot')
                            ->orWhere('soli_relatie_relatie_type.tot', '>=', $today);
                    });
            }])
            ->get()
            ->pluck('types')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->values();

        // Match against client's role mappings, pick the highest priority (lowest number)
        $bestMatch = $setting->roleMappings
            ->whereIn('relatie_type_id', $activeTypeIds)
            ->sortBy('priority')
            ->first();

        if ($bestMatch) {
            return $bestMatch->mapped_role === self::NO_ACCESS
                ? []
                : [$bestMatch->mapped_role];
        }

        if ($setting->default_role) {
            return $setting->default_role === self::NO_ACCESS
                ? []
                : [$setting->default_role];
        }

        return [];
    }
}
