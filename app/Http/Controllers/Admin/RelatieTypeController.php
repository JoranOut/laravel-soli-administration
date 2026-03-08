<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Relatie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RelatieTypeController extends Controller
{
    public function store(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'relatie_type_id' => ['required', 'exists:soli_relatie_types,id'],
            'van' => ['required', 'date'],
            'tot' => ['nullable', 'date', 'after_or_equal:van'],
            'functie' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $relatie->types()->attach($validated['relatie_type_id'], [
            'van' => $validated['van'],
            'tot' => $validated['tot'] ?? null,
            'functie' => $validated['functie'] ?? null,
            'email' => $validated['email'] ?? null,
        ]);

        return back()->with('success', __('Type added.'));
    }

    public function update(Request $request, Relatie $relatie, int $pivotId): RedirectResponse
    {
        $validated = $request->validate([
            'van' => ['required', 'date'],
            'tot' => ['nullable', 'date', 'after_or_equal:van'],
            'functie' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $relatie->types()->wherePivot('id', $pivotId)->updateExistingPivot(
            $relatie->types()->wherePivot('id', $pivotId)->first()->id,
            $validated
        );

        return back()->with('success', __('Type updated.'));
    }

    public function destroy(Relatie $relatie, int $pivotId): RedirectResponse
    {
        \DB::table('soli_relatie_relatie_type')->where('id', $pivotId)->delete();

        return back()->with('success', __('Type deleted.'));
    }
}
