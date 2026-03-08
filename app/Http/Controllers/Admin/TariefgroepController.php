<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tariefgroep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TariefgroepController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/financieel/tariefgroepen', [
            'tariefgroepen' => Tariefgroep::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'beschrijving' => ['nullable', 'string'],
        ]);

        Tariefgroep::create($validated);

        return back()->with('success', __('Fee group created.'));
    }

    public function update(Request $request, Tariefgroep $tariefgroep): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'beschrijving' => ['nullable', 'string'],
        ]);

        $tariefgroep->update($validated);

        return back()->with('success', __('Fee group updated.'));
    }

    public function destroy(Tariefgroep $tariefgroep): RedirectResponse
    {
        $tariefgroep->delete();

        return back()->with('success', __('Fee group deleted.'));
    }
}
