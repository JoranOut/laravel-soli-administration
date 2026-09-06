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

    /**
     * Set or clear the role for one relatie type.
     *
     * One type per request on purpose: the page used to submit the whole set,
     * so two admins editing at once silently overwrote each other's rows.
     */
    public function update(Request $request, RelatieType $relatieType, DerivedRoleSyncService $service): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['nullable', Rule::in($this->assignableRoles()->pluck('id')->all())],
        ]);

        DB::transaction(function () use ($relatieType, $validated) {
            if ($validated['role_id'] === null) {
                $relatieType->roleMappings()->delete();

                return;
            }

            // One row per type, enforced by a unique index on relatie_type_id
            RelatieTypeRoleMapping::updateOrCreate(
                ['relatie_type_id' => $relatieType->id],
                ['role_id' => $validated['role_id']],
            );
        });

        // Without this the change only takes effect on the nightly run
        $changed = $service->syncAll();

        return back()->with('success', __(':count user(s) updated.', ['count' => $changed]));
    }

    /**
     * Roles that may be derived from a relatie type.
     *
     * NEVER_MANAGED is withheld so a mapping can never take over the escape
     * hatch: those roles are handed out on /admin/users and nowhere else.
     */
    private function assignableRoles()
    {
        return Role::whereNotIn('name', DerivedRoleSyncService::NEVER_MANAGED)
            ->orderBy('name')
            ->get();
    }
}
