<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RelatieType;
use App\Models\RelatieTypeRoleMapping;
use App\Services\DerivedRoleSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RelatieTypeRoleMappingController extends Controller
{
    public function index(): Response
    {
        $relatieTypes = RelatieType::with('roleMappings')
            ->orderBy('naam')
            ->get()
            ->map(fn (RelatieType $type) => [
                'id' => $type->id,
                'naam' => $type->naam,
                'role_id' => $type->roleMappings->first()?->role_id,
            ]);

        return Inertia::render('admin/relatie-type-rollen', [
            'relatieTypes' => $relatieTypes,
            'roles' => $this->assignableRoles()->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
            ])->values(),
            'neverManaged' => DerivedRoleSyncService::NEVER_MANAGED,
        ]);
    }

    public function update(Request $request, DerivedRoleSyncService $service): RedirectResponse
    {
        $assignableRoleIds = $this->assignableRoles()->pluck('id')->all();

        $validated = $request->validate([
            'mappings' => ['present', 'array'],
            'mappings.*.relatie_type_id' => [
                'required',
                'exists:soli_relatie_types,id',
                // One role per type, so a type must not appear twice
                'distinct',
            ],
            'mappings.*.role_id' => ['required', Rule::in($assignableRoleIds)],
        ]);

        DB::transaction(function () use ($validated) {
            RelatieTypeRoleMapping::query()->delete();

            foreach ($validated['mappings'] as $mapping) {
                RelatieTypeRoleMapping::create([
                    'relatie_type_id' => $mapping['relatie_type_id'],
                    'role_id' => $mapping['role_id'],
                ]);
            }
        });

        // Without this the new rule only takes effect on the nightly run.
        $changed = $service->syncAll();

        return back()->with('success', __(':count user(s) updated.', ['count' => $changed]));
    }

    /**
     * Roles that may be derived from a relatie type.
     *
     * admin and member are withheld so a mapping can never take over the
     * escape hatch or fight the member role the relatie flow assigns.
     */
    private function assignableRoles()
    {
        return Role::whereNotIn('name', DerivedRoleSyncService::NEVER_MANAGED)
            ->orderBy('name')
            ->get();
    }
}
