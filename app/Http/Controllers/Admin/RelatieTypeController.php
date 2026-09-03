<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGoogleContactsJob;
use App\Models\Relatie;
use App\Services\DerivedRoleSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RelatieTypeController extends Controller
{
    public function __construct(private readonly DerivedRoleSyncService $derivedRoles) {}

    /**
     * A type change can grant or revoke a derived role for the linked user.
     */
    private function syncDerivedRoles(Relatie $relatie): void
    {
        $user = $relatie->fresh()?->user;

        if ($user) {
            $this->derivedRoles->syncUser($user->load('roles'));
        }
    }

    public function store(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'relatie_type_id' => ['required', 'exists:soli_relatie_types,id'],
            'van' => ['required', 'date'],
            'tot' => ['nullable', 'date', 'after_or_equal:van'],
            'functie' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'onderdeel_id' => ['nullable', 'exists:soli_onderdelen,id'],
        ]);

        $relatie->types()->attach($validated['relatie_type_id'], [
            'van' => $validated['van'],
            'tot' => $validated['tot'] ?? null,
            'functie' => $validated['functie'] ?? null,
            'email' => $validated['email'] ?? null,
            'onderdeel_id' => $validated['onderdeel_id'] ?? null,
        ]);

        $this->syncDerivedRoles($relatie);

        SyncGoogleContactsJob::dispatch($relatie->id)->afterResponse();

        return back()->with('success', __('Type added.'));
    }

    public function update(Request $request, Relatie $relatie, int $pivotId): RedirectResponse
    {
        $validated = $request->validate([
            'van' => ['required', 'date'],
            'tot' => ['nullable', 'date', 'after_or_equal:van'],
            'functie' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'onderdeel_id' => ['nullable', 'exists:soli_onderdelen,id'],
        ]);

        $type = $relatie->types()->wherePivot('id', $pivotId)->first();

        if (! $type) {
            abort(404);
        }

        $relatie->types()->wherePivot('id', $pivotId)->updateExistingPivot(
            $type->id,
            $validated
        );

        $this->syncDerivedRoles($relatie);

        SyncGoogleContactsJob::dispatch($relatie->id)->afterResponse();

        return back()->with('success', __('Type updated.'));
    }

    public function destroy(Relatie $relatie, int $pivotId): RedirectResponse
    {
        $type = $relatie->types()->wherePivot('id', $pivotId)->first();

        if (! $type) {
            abort(404);
        }

        $relatie->types()->wherePivot('id', $pivotId)->detach($type->id);

        $this->syncDerivedRoles($relatie);

        SyncGoogleContactsJob::dispatch($relatie->id)->afterResponse();

        return back()->with('success', __('Type deleted.'));
    }
}
