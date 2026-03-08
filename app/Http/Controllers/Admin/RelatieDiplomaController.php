<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Diploma;
use App\Models\Relatie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RelatieDiplomaController extends Controller
{
    public function store(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'instrument' => ['nullable', 'string', 'max:255'],
        ]);

        $relatie->diplomas()->create($validated);

        return back()->with('success', __('Diploma added.'));
    }

    public function update(Request $request, Relatie $relatie, Diploma $diploma): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'instrument' => ['nullable', 'string', 'max:255'],
        ]);

        $diploma->update($validated);

        return back()->with('success', __('Diploma updated.'));
    }

    public function destroy(Relatie $relatie, Diploma $diploma): RedirectResponse
    {
        $diploma->delete();

        return back()->with('success', __('Diploma deleted.'));
    }
}
