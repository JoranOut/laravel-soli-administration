<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instrument;
use App\Models\Relatie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentController extends Controller
{
    public function index(Request $request): Response
    {
        $allowedSorts = ['nummer', 'soort', 'merk', 'status'];
        $sort = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'nummer';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $instrumenten = Instrument::query()
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('nummer', 'like', "%{$search}%")
                        ->orWhere('soort', 'like', "%{$search}%")
                        ->orWhere('merk', 'like', "%{$search}%");
                });
            })
            ->with('huidigeBespeler.relatie')
            ->orderBy($sort, $direction)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/instrumenten/index', [
            'instrumenten' => $instrumenten,
            'filters' => $request->only(['search', 'status', 'sort', 'direction']),
        ]);
    }

    public function show(Instrument $instrument): Response
    {
        $instrument->load([
            'huidigeBespeler.relatie',
            'bespelers.relatie',
            'bijzonderheden' => fn ($q) => $q->orderByDesc('datum'),
            'reparaties' => fn ($q) => $q->orderByDesc('datum_in'),
        ]);

        $relaties = Relatie::actief()->orderBy('achternaam')->get(['id', 'voornaam', 'tussenvoegsel', 'achternaam']);

        return Inertia::render('admin/instrumenten/show', [
            'instrument' => $instrument,
            'relaties' => $relaties,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nummer' => ['required', 'string', 'max:50', 'unique:soli_instrumenten,nummer'],
            'soort' => ['required', 'string', 'max:255'],
            'merk' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serienummer' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:beschikbaar,in_gebruik,in_reparatie,afgeschreven'],
            'eigendom' => ['required', 'in:soli,bruikleen,eigen'],
            'aanschafjaar' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'prijs' => ['nullable', 'numeric', 'min:0'],
            'locatie' => ['nullable', 'string', 'max:255'],
        ]);

        $instrument = Instrument::create($validated);

        return redirect()
            ->route('admin.instrumenten.show', $instrument)
            ->with('success', __('Instrument created.'));
    }

    public function update(Request $request, Instrument $instrument): RedirectResponse
    {
        $validated = $request->validate([
            'nummer' => ['required', 'string', 'max:50', "unique:soli_instrumenten,nummer,{$instrument->id}"],
            'soort' => ['required', 'string', 'max:255'],
            'merk' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serienummer' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:beschikbaar,in_gebruik,in_reparatie,afgeschreven'],
            'eigendom' => ['required', 'in:soli,bruikleen,eigen'],
            'aanschafjaar' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'prijs' => ['nullable', 'numeric', 'min:0'],
            'locatie' => ['nullable', 'string', 'max:255'],
        ]);

        $instrument->update($validated);

        return back()->with('success', __('Instrument updated.'));
    }

    public function destroy(Instrument $instrument): RedirectResponse
    {
        $instrument->delete();

        return redirect()->route('admin.instrumenten.index')->with('success', __('Instrument deleted.'));
    }
}
