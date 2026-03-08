<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Onderdeel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnderdeelController extends Controller
{
    public function index(Request $request): Response
    {
        $onderdelen = Onderdeel::query()
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->when(! $request->boolean('show_inactive'), fn ($q) => $q->actief())
            ->withCount(['relaties as actieve_relaties_count' => function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('soli_relatie_onderdeel.tot')
                        ->orWhere('soli_relatie_onderdeel.tot', '>=', now()->toDateString());
                });
            }])
            ->orderBy('naam')
            ->get();

        return Inertia::render('admin/onderdelen/index', [
            'onderdelen' => $onderdelen,
            'filters' => $request->only(['type', 'show_inactive']),
        ]);
    }

    public function show(Onderdeel $onderdeel): Response
    {
        $onderdeel->load([
            'relaties' => fn ($q) => $q
                ->where(function ($q2) {
                    $q2->whereNull('soli_relatie_onderdeel.tot')
                        ->orWhere('soli_relatie_onderdeel.tot', '>=', now()->toDateString());
                }),
        ]);

        return Inertia::render('admin/onderdelen/show', [
            'onderdeel' => $onderdeel,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:orkest,opleidingsgroep,ensemble,commissie,bestuur,staff,overig'],
            'beschrijving' => ['nullable', 'string'],
        ]);

        Onderdeel::create($validated);

        return back()->with('success', __('Section created.'));
    }

    public function update(Request $request, Onderdeel $onderdeel): RedirectResponse
    {
        $validated = $request->validate([
            'naam' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:orkest,opleidingsgroep,ensemble,commissie,bestuur,staff,overig'],
            'beschrijving' => ['nullable', 'string'],
            'actief' => ['boolean'],
        ]);

        $onderdeel->update($validated);

        return back()->with('success', __('Section updated.'));
    }

    public function destroy(Onderdeel $onderdeel): RedirectResponse
    {
        $onderdeel->delete();

        return redirect()->route('admin.onderdelen.index')->with('success', __('Section deleted.'));
    }
}
