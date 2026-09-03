<?php

namespace Database\Seeders;

use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\User;
use App\Services\DerivedRoleSyncService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ])->assignRole('admin');

        User::factory()->create([
            'name' => 'Ledenadministratie User',
            'email' => 'ledenadministratie@example.com',
        ])->assignRole('ledenadministratie');

        $memberUser = User::factory()->create([
            'name' => 'Member User',
            'email' => 'member@example.com',
        ]);
        $memberUser->assignRole('member');

        $this->call([
            RelatieTypeSeeder::class,
            OnderdeelSeeder::class,
            InstrumentSoortSeeder::class,
            SampleDataSeeder::class,
            OauthClientSeeder::class,
            ClientRoleMappingSeeder::class,
            RelatieTypeRoleMappingSeeder::class,
            GoogleContactSyncLogSeeder::class,
        ]);

        // Link the member user to a relatie without a mapped type, so this
        // account keeps demonstrating a plain member. Picking Relatie::first()
        // handed it a bestuur type and with it the bestuur role.
        $plainRelatie = Relatie::actief()
            ->whereDoesntHave('types.roleMappings')
            ->first();

        if ($plainRelatie) {
            // Delete the auto-created user for this relatie
            if ($plainRelatie->user_id) {
                User::find($plainRelatie->user_id)?->delete();
            }
            $plainRelatie->update(['user_id' => $memberUser->id]);
        }

        // A login for the contactpersoon role, which it earns through the type
        // rather than through assignRole
        $contactpersoonType = RelatieType::where('naam', 'contactpersoon')->first();

        if ($contactpersoonType) {
            $contactUser = User::factory()->create([
                'name' => 'Contactpersoon User',
                'email' => 'contactpersoon@example.com',
            ]);

            $contactRelatie = Relatie::factory()->create(['user_id' => $contactUser->id]);
            $contactRelatie->types()->attach($contactpersoonType->id, [
                'van' => now()->subYear()->toDateString(),
                'functie' => 'Contactpersoon Harmonie',
                'email' => 'contactpersoon@soli.nl',
            ]);
        }

        // Apply the relatie type mappings, so the seeded bestuur and
        // contactpersoon accounts hold their roles without waiting for the
        // nightly roles:sync-derived run.
        app(DerivedRoleSyncService::class)->syncAll();
    }
}
