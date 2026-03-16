<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Opleiding;
use App\Models\Relatie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RelatieOpleidingController extends Controller
{
    public function store(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'instituut' => ['nullable', 'string', 'max:255'],
            'instrument' => ['nullable', 'string', 'max:255'],
            'diploma' => ['nullable', 'string', 'max:255'],
            'datum_start' => ['nullable', 'date'],
            'datum_einde' => ['nullable', 'date', 'after_or_equal:datum_start'],
            'opmerking' => ['nullable', 'string'],
        ]);

        $relatie->opleidingen()->create($validated);

        return back()->with('success', __('Training added.'));
    }

    public function update(Request $request, Relatie $relatie, Opleiding $opleiding): RedirectResponse
    {
        abort_unless($opleiding->relatie_id === $relatie->id, 404);

        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'instituut' => ['nullable', 'string', 'max:255'],
            'instrument' => ['nullable', 'string', 'max:255'],
            'diploma' => ['nullable', 'string', 'max:255'],
            'datum_start' => ['nullable', 'date'],
            'datum_einde' => ['nullable', 'date', 'after_or_equal:datum_start'],
            'opmerking' => ['nullable', 'string'],
        ]);

        $opleiding->update($validated);

        return back()->with('success', __('Training updated.'));
    }

    public function destroy(Relatie $relatie, Opleiding $opleiding): RedirectResponse
    {
        abort_unless($opleiding->relatie_id === $relatie->id, 404);

        $opleiding->delete();

        return back()->with('success', __('Training deleted.'));
    }
}
