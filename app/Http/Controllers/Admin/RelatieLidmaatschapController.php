<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Relatie;
use App\Models\RelatieSinds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RelatieLidmaatschapController extends Controller
{
    public function storeLidmaatschap(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'lid_sinds' => ['required', 'date'],
            'lid_tot' => ['nullable', 'date', 'after_or_equal:lid_sinds'],
            'reden_vertrek' => ['nullable', 'string', 'max:255'],
        ]);

        $relatie->relatieSinds()->create($validated);

        return back()->with('success', __('Membership period added.'));
    }

    public function updateLidmaatschap(Request $request, Relatie $relatie, RelatieSinds $relatieSinds): RedirectResponse
    {
        $validated = $request->validate([
            'lid_sinds' => ['required', 'date'],
            'lid_tot' => ['nullable', 'date', 'after_or_equal:lid_sinds'],
            'reden_vertrek' => ['nullable', 'string', 'max:255'],
        ]);

        $relatieSinds->update($validated);

        return back()->with('success', __('Membership period updated.'));
    }

    public function destroyLidmaatschap(Relatie $relatie, RelatieSinds $relatieSinds): RedirectResponse
    {
        $relatieSinds->delete();

        return back()->with('success', __('Membership period deleted.'));
    }

    public function storeOnderdeel(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'onderdeel_id' => ['required', 'exists:soli_onderdelen,id'],
            'functie' => ['nullable', 'string', 'max:255'],
            'van' => ['required', 'date'],
            'tot' => ['nullable', 'date', 'after_or_equal:van'],
        ]);

        $relatie->onderdelen()->attach($validated['onderdeel_id'], [
            'functie' => $validated['functie'] ?? null,
            'van' => $validated['van'],
            'tot' => $validated['tot'] ?? null,
        ]);

        return back()->with('success', __('Section added.'));
    }

    public function updateOnderdeel(Request $request, Relatie $relatie, int $pivotId): RedirectResponse
    {
        $validated = $request->validate([
            'functie' => ['nullable', 'string', 'max:255'],
            'van' => ['required', 'date'],
            'tot' => ['nullable', 'date', 'after_or_equal:van'],
        ]);

        \DB::table('soli_relatie_onderdeel')
            ->where('id', $pivotId)
            ->update($validated);

        return back()->with('success', __('Section updated.'));
    }

    public function destroyOnderdeel(Relatie $relatie, int $pivotId): RedirectResponse
    {
        \DB::table('soli_relatie_onderdeel')->where('id', $pivotId)->delete();

        return back()->with('success', __('Section deleted.'));
    }
}
