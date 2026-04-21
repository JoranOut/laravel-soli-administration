<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOnderdeelRequest;
use App\Http\Requests\UpdateOnderdeelRequest;
use App\Models\Onderdeel;
use App\Models\RelatieInstrument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnderdeelController extends Controller
{
    public function index(Request $request): Response
    {
        $onderdelen = Onderdeel::query()
            ->when($request->input('search'), function ($q, $search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('naam', 'like', "%{$search}%")
                        ->orWhere('afkorting', 'like', "%{$search}%");
                });
            })
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
            'filters' => $request->only(['type', 'show_inactive', 'search']),
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
            'relaties.types' => fn ($q) => $q
                ->where(function ($q2) {
                    $q2->whereNull('soli_relatie_relatie_type.tot')
                        ->orWhere('soli_relatie_relatie_type.tot', '>=', now()->toDateString());
                })
                ->where(function ($q2) use ($onderdeel) {
                    $q2->where('soli_relatie_relatie_type.onderdeel_id', $onderdeel->id)
                        ->orWhereNull('soli_relatie_relatie_type.onderdeel_id');
                }),
            'relaties.emails',
        ]);

        $instrumentsByRelatie = RelatieInstrument::where('onderdeel_id', $onderdeel->id)
            ->with('instrumentSoort')
            ->get()
            ->groupBy('relatie_id')
            ->map(fn ($items) => $items->pluck('instrumentSoort.naam')->toArray());

        return Inertia::render('admin/onderdelen/show', [
            'onderdeel' => $onderdeel,
            'instrumentsByRelatie' => $instrumentsByRelatie,
        ]);
    }

    public function store(StoreOnderdeelRequest $request): RedirectResponse
    {
        $onderdeel = Onderdeel::create($request->validated());

        return redirect()->route('admin.onderdelen.show', $onderdeel)->with('success', __('Section created.'));
    }

    public function update(UpdateOnderdeelRequest $request, Onderdeel $onderdeel): RedirectResponse
    {
        $onderdeel->update($request->validated());

        return back()->with('success', __('Section updated.'));
    }

    public function destroy(Onderdeel $onderdeel): RedirectResponse
    {
        $activeCount = $onderdeel->actieveRelaties()->count();

        if ($activeCount > 0) {
            return back()->with('error', __('Cannot delete a section with active members.'));
        }

        $onderdeel->delete();

        return redirect()->route('admin.onderdelen.index')->with('success', __('Section deleted.'));
    }
}
