<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contributie;
use App\Models\SoortContributie;
use App\Models\Tariefgroep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContributieController extends Controller
{
    public function index(Request $request): Response
    {
        $jaar = $request->input('jaar', now()->year);

        $contributies = Contributie::with(['tariefgroep', 'soortContributie'])
            ->where('jaar', $jaar)
            ->get();

        $beschikbareJaren = Contributie::selectRaw('DISTINCT jaar')
            ->orderByDesc('jaar')
            ->pluck('jaar');

        return Inertia::render('admin/financieel/contributies', [
            'contributies' => $contributies,
            'tariefgroepen' => Tariefgroep::all(),
            'soortContributies' => SoortContributie::all(),
            'jaar' => (int) $jaar,
            'beschikbareJaren' => $beschikbareJaren,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'tariefgroep_id' => ['required', 'exists:soli_tariefgroepen,id'],
            'soort_contributie_id' => ['required', 'exists:soli_soort_contributies,id'],
            'jaar' => ['required', 'integer', 'min:2000', 'max:2100'],
            'bedrag' => ['required', 'numeric', 'min:0'],
        ]);

        Contributie::updateOrCreate(
            [
                'tariefgroep_id' => $validated['tariefgroep_id'],
                'soort_contributie_id' => $validated['soort_contributie_id'],
                'jaar' => $validated['jaar'],
            ],
            ['bedrag' => $validated['bedrag']]
        );

        return back()->with('success', __('Contribution saved.'));
    }

    public function destroy(Contributie $contributie): RedirectResponse
    {
        $contributie->delete();

        return back()->with('success', __('Contribution deleted.'));
    }
}
