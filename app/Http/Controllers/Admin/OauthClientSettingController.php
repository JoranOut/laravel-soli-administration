<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRoleMapping;
use App\Models\OauthClientSetting;
use App\Models\OauthClientUserRole;
use App\Models\RelatieType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Client;

class OauthClientSettingController extends Controller
{
    public function index(): Response
    {
        $clients = Client::where('revoked', false)
            ->get()
            ->map(function (Client $client) {
                $setting = OauthClientSetting::with(['roleMappings.relatieType', 'userRoles.user:id,name,email'])
                    ->where('client_id', $client->id)
                    ->first();

                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'redirect_uris' => $client->redirect_uris,
                    'setting' => $setting ? [
                        'id' => $setting->id,
                        'type' => $setting->type,
                        'default_role' => $setting->default_role,
                        'skip_authorization' => (bool) $setting->skip_authorization,
                        'role_mappings' => $setting->roleMappings->sortBy('priority')->values()->map(fn (ClientRoleMapping $m) => [
                            'id' => $m->id,
                            'relatie_type_id' => $m->relatie_type_id,
                            'relatie_type_naam' => $m->relatieType->naam,
                            'mapped_role' => $m->mapped_role,
                            'priority' => $m->priority,
                        ]),
                        'user_roles' => $setting->userRoles->values()->map(fn (OauthClientUserRole $u) => [
                            'id' => $u->id,
                            'user_id' => $u->user_id,
                            'user_name' => $u->user?->name ?? '',
                            'mapped_role' => $u->mapped_role,
                        ]),
                    ] : null,
                ];
            });

        $relatieTypes = RelatieType::orderBy('naam')->get(['id', 'naam']);
        $users = User::orderBy('name')->get(['id', 'name', 'email']);

        return Inertia::render('admin/oauth-clients/index', [
            'clients' => $clients,
            'relatieTypes' => $relatieTypes,
            'users' => $users,
        ]);
    }

    public function update(Request $request, string $clientId)
    {
        $client = Client::findOrFail($clientId);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:50'],
            'default_role' => ['nullable', 'string', 'max:100'],
            'skip_authorization' => ['boolean'],
            'role_mappings' => ['present', 'array'],
            'role_mappings.*.relatie_type_id' => ['required', 'exists:soli_relatie_types,id'],
            'role_mappings.*.mapped_role' => ['required', 'string', 'max:100'],
            'user_roles' => ['present', 'array'],
            'user_roles.*.user_id' => ['required', 'exists:users,id'],
            'user_roles.*.mapped_role' => ['required', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($client, $validated) {
            $setting = OauthClientSetting::updateOrCreate(
                ['client_id' => $client->id],
                [
                    'type' => $validated['type'],
                    'default_role' => $validated['default_role'],
                    'skip_authorization' => $validated['skip_authorization'] ?? false,
                ]
            );

            // Sync role mappings: delete existing, insert new
            $setting->roleMappings()->delete();

            foreach ($validated['role_mappings'] as $index => $mapping) {
                $setting->roleMappings()->create([
                    'relatie_type_id' => $mapping['relatie_type_id'],
                    'mapped_role' => $mapping['mapped_role'],
                    'priority' => $index,
                ]);
            }

            // Sync user-specific role overrides: delete existing, insert new
            $setting->userRoles()->delete();

            foreach ($validated['user_roles'] as $userRole) {
                $setting->userRoles()->create([
                    'user_id' => $userRole['user_id'],
                    'mapped_role' => $userRole['mapped_role'],
                ]);
            }
        });

        return back();
    }
}
