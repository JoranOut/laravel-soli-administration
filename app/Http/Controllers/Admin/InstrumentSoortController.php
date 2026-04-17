<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstrumentFamilie;
use App\Models\InstrumentSoort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentSoortController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/instrumentsoorten/index', [
            'instrumentSoorten' => InstrumentSoort::with('instrumentFamilie')
                ->withCount('relatieInstrumenten')
                ->orderBy('instrument_familie_id')
                ->orderBy('naam')
                ->get(),
            'families' => InstrumentFamilie::orderBy('naam')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255', Rule::unique('soli_instrument_soorten', 'naam')],
            'instrument_familie_id' => ['required', 'exists:soli_instrument_families,id'],
        ]);

        InstrumentSoort::create($validated);

        return back()->with('success', __('Instrument type created.'));
    }

    public function update(Request $request, InstrumentSoort $instrumentSoort): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255', Rule::unique('soli_instrument_soorten', 'naam')->ignore($instrumentSoort->id)],
            'instrument_familie_id' => ['required', 'exists:soli_instrument_families,id'],
        ]);

        $instrumentSoort->update($validated);

        return back()->with('success', __('Instrument type updated.'));
    }

    public function destroy(InstrumentSoort $instrumentSoort): RedirectResponse
    {
        if ($instrumentSoort->relatieInstrumenten()->exists()) {
            return back()->with('error', __('Cannot delete an instrument type with linked members.'));
        }

        $instrumentSoort->delete();

        return back()->with('success', __('Instrument type deleted.'));
    }

    public function storeFamily(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255', Rule::unique('soli_instrument_families', 'naam')],
        ]);

        InstrumentFamilie::create($validated);

        return back()->with('success', __('Family created.'));
    }

    public function updateFamily(Request $request, InstrumentFamilie $instrumentFamilie): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255', Rule::unique('soli_instrument_families', 'naam')->ignore($instrumentFamilie->id)],
        ]);

        $instrumentFamilie->update($validated);

        return back()->with('success', __('Family updated.'));
    }

    public function destroyFamily(InstrumentFamilie $instrumentFamilie): RedirectResponse
    {
        if ($instrumentFamilie->instrumentSoorten()->exists()) {
            return back()->with('error', __('Cannot delete a family with linked instrument types.'));
        }

        $instrumentFamilie->delete();

        return back()->with('success', __('Family deleted.'));
    }
}
