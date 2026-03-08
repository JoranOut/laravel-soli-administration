<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Insigne;
use App\Models\Relatie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RelatieInsigneController extends Controller
{
    public function store(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'datum' => ['required', 'date'],
        ]);

        $relatie->insignes()->create($validated);

        return back()->with('success', __('Badge added.'));
    }

    public function update(Request $request, Relatie $relatie, Insigne $insigne): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'datum' => ['required', 'date'],
        ]);

        $insigne->update($validated);

        return back()->with('success', __('Badge updated.'));
    }

    public function destroy(Relatie $relatie, Insigne $insigne): RedirectResponse
    {
        $insigne->delete();

        return back()->with('success', __('Badge deleted.'));
    }
}
