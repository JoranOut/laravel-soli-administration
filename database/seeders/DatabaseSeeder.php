<?php

namespace Database\Seeders;

use App\Models\Relatie;
use App\Models\User;
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
            TariefgroepSeeder::class,
            SoortContributieSeeder::class,
            SampleDataSeeder::class,
            ClientRoleMappingSeeder::class,
            GoogleContactSyncLogSeeder::class,
        ]);

        // Link the member user to the first relatie (replace auto-created user)
        $firstRelatie = Relatie::first();
        if ($firstRelatie) {
            // Delete the auto-created user for this relatie
            if ($firstRelatie->user_id) {
                User::find($firstRelatie->user_id)?->delete();
            }
            $firstRelatie->update(['user_id' => $memberUser->id]);
        }
    }
}
