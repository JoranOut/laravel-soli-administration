<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRoleMapping;
use App\Models\OauthClientSetting;
use App\Models\RelatieType;
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
                $setting = OauthClientSetting::with('roleMappings.relatieType')
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
                    ] : null,
                ];
            });

        $relatieTypes = RelatieType::orderBy('naam')->get(['id', 'naam']);

        return Inertia::render('admin/oauth-clients/index', [
            'clients' => $clients,
            'relatieTypes' => $relatieTypes,
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
        });

        return back();
    }
}
