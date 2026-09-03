<?php

namespace Database\Seeders;

use App\Models\RelatieType;
use App\Models\RelatieTypeRoleMapping;
use App\Services\DerivedRoleSyncService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RelatieTypeRoleMappingSeeder extends Seeder
{
    /**
     * Relatie type name => internal role name.
     *
     * Roles in DerivedRoleSyncService::NEVER_MANAGED are skipped on purpose.
     */
    private const MAPPINGS = [
        'bestuur' => 'bestuur',
        'contactpersoon' => 'contactpersoon',
    ];

    public function run(): void
    {
        foreach (self::MAPPINGS as $typeNaam => $roleName) {
            if (in_array($roleName, DerivedRoleSyncService::NEVER_MANAGED, true)) {
                continue;
            }

            $type = RelatieType::where('naam', $typeNaam)->first();
            $role = Role::where('name', $roleName)->first();

            if (! $type || ! $role) {
                continue;
            }

            RelatieTypeRoleMapping::updateOrCreate([
                'relatie_type_id' => $type->id,
                'role_id' => $role->id,
            ]);
        }
    }
}
