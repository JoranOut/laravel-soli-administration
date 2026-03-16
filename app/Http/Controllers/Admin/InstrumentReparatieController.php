<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Models\InstrumentReparatie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstrumentReparatieController extends Controller
{
    public function store(Request $request, Instrument $instrument): RedirectResponse
    {
        $validated = $request->validate([
            'beschrijving' => ['required', 'string'],
            'reparateur' => ['nullable', 'string', 'max:255'],
            'kosten' => ['nullable', 'numeric', 'min:0'],
            'datum_in' => ['required', 'date'],
            'datum_uit' => ['nullable', 'date', 'after_or_equal:datum_in'],
        ]);

        $instrument->reparaties()->create($validated);

        if (empty($validated['datum_uit'])) {
            $instrument->update(['status' => 'in_reparatie']);
        }

        return back()->with('success', __('Repair added.'));
    }

    public function update(Request $request, Instrument $instrument, InstrumentReparatie $reparatie): RedirectResponse
    {
        abort_unless($reparatie->instrument_id === $instrument->id, 404);

        $validated = $request->validate([
            'beschrijving' => ['required', 'string'],
            'reparateur' => ['nullable', 'string', 'max:255'],
            'kosten' => ['nullable', 'numeric', 'min:0'],
            'datum_in' => ['required', 'date'],
            'datum_uit' => ['nullable', 'date', 'after_or_equal:datum_in'],
        ]);

        $reparatie->update($validated);

        return back()->with('success', __('Repair updated.'));
    }

    public function destroy(Instrument $instrument, InstrumentReparatie $reparatie): RedirectResponse
    {
        abort_unless($reparatie->instrument_id === $instrument->id, 404);

        $reparatie->delete();

        return back()->with('success', __('Repair deleted.'));
    }
}
