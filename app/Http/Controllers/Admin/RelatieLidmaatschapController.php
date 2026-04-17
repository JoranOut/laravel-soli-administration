<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncGoogleContactsJob;
use App\Models\Relatie;
use App\Models\RelatieInstrument;
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
        abort_unless($relatieSinds->relatie_id === $relatie->id, 404);

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
        abort_unless($relatieSinds->relatie_id === $relatie->id, 404);

        $relatieSinds->delete();

        return back()->with('success', __('Membership period deleted.'));
    }

    public function storeOnderdeel(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'onderdeel_id' => ['required', 'exists:soli_onderdelen,id'],
            'functie' => ['nullable', 'string', 'max:255'],
            'instrument_soort_ids' => ['nullable', 'array'],
            'instrument_soort_ids.*' => ['integer', 'exists:soli_instrument_soorten,id'],
            'van' => ['required', 'date'],
            'tot' => ['nullable', 'date', 'after_or_equal:van'],
        ]);

        $relatie->onderdelen()->attach($validated['onderdeel_id'], [
            'functie' => $validated['functie'] ?? null,
            'van' => $validated['van'],
            'tot' => $validated['tot'] ?? null,
        ]);

        foreach ($validated['instrument_soort_ids'] ?? [] as $instrumentSoortId) {
            RelatieInstrument::firstOrCreate([
                'relatie_id' => $relatie->id,
                'onderdeel_id' => $validated['onderdeel_id'],
                'instrument_soort_id' => $instrumentSoortId,
            ]);
        }

        SyncGoogleContactsJob::dispatch($relatie->id)->afterResponse();

        return back()->with('success', __('Section added.'));
    }

    public function updateOnderdeel(Request $request, Relatie $relatie, int $pivotId): RedirectResponse
    {
        $validated = $request->validate([
            'functie' => ['nullable', 'string', 'max:255'],
            'instrument_soort_ids' => ['nullable', 'array'],
            'instrument_soort_ids.*' => ['integer', 'exists:soli_instrument_soorten,id'],
            'van' => ['required', 'date'],
            'tot' => ['nullable', 'date', 'after_or_equal:van'],
        ]);

        $onderdeel = $relatie->onderdelen()->wherePivot('id', $pivotId)->first();
        abort_unless($onderdeel, 404);

        $relatie->onderdelen()->updateExistingPivot($onderdeel->id, [
            'functie' => $validated['functie'] ?? null,
            'van' => $validated['van'],
            'tot' => $validated['tot'] ?? null,
        ]);

        if (array_key_exists('instrument_soort_ids', $validated)) {
            RelatieInstrument::where('relatie_id', $relatie->id)
                ->where('onderdeel_id', $onderdeel->id)
                ->delete();

            foreach ($validated['instrument_soort_ids'] ?? [] as $instrumentSoortId) {
                RelatieInstrument::create([
                    'relatie_id' => $relatie->id,
                    'onderdeel_id' => $onderdeel->id,
                    'instrument_soort_id' => $instrumentSoortId,
                ]);
            }
        }

        SyncGoogleContactsJob::dispatch($relatie->id)->afterResponse();

        return back()->with('success', __('Section updated.'));
    }

    public function destroyOnderdeel(Relatie $relatie, int $pivotId): RedirectResponse
    {
        $onderdeel = $relatie->onderdelen()->wherePivot('id', $pivotId)->first();
        abort_unless($onderdeel, 404);

        $relatie->onderdelen()->wherePivot('id', $pivotId)->detach();

        $remainingPivots = $relatie->onderdelen()->where('soli_onderdelen.id', $onderdeel->id)->count();
        if ($remainingPivots === 0) {
            RelatieInstrument::where('relatie_id', $relatie->id)
                ->where('onderdeel_id', $onderdeel->id)
                ->delete();
        }

        SyncGoogleContactsJob::dispatch($relatie->id)->afterResponse();

        return back()->with('success', __('Section deleted.'));
    }
}
