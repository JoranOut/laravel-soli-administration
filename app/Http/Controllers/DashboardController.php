<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = auth()->user();

        if ($user->hasRole('member') && ! $user->hasRole('admin') && ! $user->hasRole('bestuur') && ! $user->hasRole('ledenadministratie')) {
            return $this->memberDashboard($user, $request);
        }

        return $this->statisticsDashboard();
    }

    private function memberDashboard($user, Request $request): Response
    {
        $userRelaties = $user->relaties()->orderBy('achternaam')->get(['id', 'voornaam', 'tussenvoegsel', 'achternaam', 'relatie_nummer']);

        if ($userRelaties->isEmpty()) {
            return Inertia::render('admin/relaties/not-linked');
        }

        $relatieId = $request->input('relatie');
        $relatie = $relatieId
            ? $userRelaties->firstWhere('id', (int) $relatieId)
            : null;

        // Fall back to first relatie if invalid or not provided
        $relatie = $relatie ? Relatie::find($relatie->id) : Relatie::find($userRelaties->first()->id);

        $relatie->load([
            'user',
            'types',
            'adressen' => fn ($q) => $q->orderByDesc('created_at'),
            'emails' => fn ($q) => $q->orderByDesc('created_at'),
            'telefoons' => fn ($q) => $q->orderByDesc('created_at'),
            'giroGegevens' => fn ($q) => $q->orderByDesc('created_at'),
            'relatieSinds' => fn ($q) => $q->orderByDesc('lid_sinds'),
            'onderdelen' => fn ($q) => $q->orderByDesc('soli_relatie_onderdeel.van'),
            'relatieInstrumenten.onderdeel',
            'instrumentBespelers.instrument',
            'opleidingen' => fn ($q) => $q->orderByDesc('datum_start'),
            'uniformen' => fn ($q) => $q->orderByDesc('van'),
            'insignes' => fn ($q) => $q->orderByDesc('datum'),
            'diplomas' => fn ($q) => $q->orderBy('naam'),
            'andereVerenigingen' => fn ($q) => $q->orderByDesc('van'),
            'teBetakenContributies.contributie.soortContributie',
            'teBetakenContributies.contributie.tariefgroep',
            'teBetakenContributies.betalingen',
        ]);

        return Inertia::render('admin/relaties/show', [
            'relatie' => $relatie,
            'relatieTypes' => RelatieType::all(),
            'onderdelen' => Onderdeel::actief()->orderBy('naam')->get(),
            'userRelaties' => $userRelaties,
        ]);
    }

    private function statisticsDashboard(): Response
    {
        $actieveLeden = Relatie::actief()->ofType('lid')->count();
        $donateurs = Relatie::actief()->ofType('donateur')->count();
        $instrumentenInGebruik = Instrument::inGebruik()->count();
        $openstaandeReparaties = Instrument::inReparatie()->count();

        $data = [
            'stats' => [
                'actieve_leden' => $actieveLeden,
                'donateurs' => $donateurs,
                'instrumenten_in_gebruik' => $instrumentenInGebruik,
                'openstaande_reparaties' => $openstaandeReparaties,
            ],
        ];

        if (auth()->user()->hasRole('admin')) {
            $data['alerts'] = [
                'unlinked_users' => User::whereDoesntHave('relaties')->count(),
                'unlinked_relaties' => Relatie::actief()->whereNull('user_id')->count(),
            ];
        }

        return Inertia::render('dashboard', $data);
    }
}
