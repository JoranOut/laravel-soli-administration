<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Models\InstrumentBespeler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstrumentBespelerController extends Controller
{
    public function store(Request $request, Instrument $instrument): RedirectResponse
    {
        $validated = $request->validate([
            'relatie_id' => ['required', 'exists:soli_relaties,id'],
            'van' => ['required', 'date'],
        ]);

        // Close current bespeler if exists
        $instrument->huidigeBespeler()?->update(['tot' => $validated['van']]);

        InstrumentBespeler::create([
            'instrument_id' => $instrument->id,
            'relatie_id' => $validated['relatie_id'],
            'van' => $validated['van'],
        ]);

        $instrument->update(['status' => 'in_gebruik']);

        return back()->with('success', __('Instrument assigned.'));
    }

    public function update(Request $request, Instrument $instrument, InstrumentBespeler $bespeler): RedirectResponse
    {
        abort_unless($bespeler->instrument_id === $instrument->id, 404);

        $validated = $request->validate([
            'van' => ['required', 'date'],
        ]);

        $bespeler->update($validated);

        return back()->with('success', __('Player updated.'));
    }

    public function destroy(Instrument $instrument, InstrumentBespeler $bespeler): RedirectResponse
    {
        abort_unless($bespeler->instrument_id === $instrument->id, 404);

        $bespeler->update(['tot' => now()->toDateString()]);

        // If no active bespelers, set to available
        if (! $instrument->huidigeBespeler()->exists()) {
            $instrument->update(['status' => 'beschikbaar']);
        }

        return back()->with('success', __('Instrument returned.'));
    }
}
