<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    private function getOnderdeelHistory(): array
    {
        $onderdelen = Onderdeel::whereIn('type', ['orkest', 'ensemble'])
            ->orderBy('naam')
            ->get(['id', 'naam']);

        if ($onderdelen->isEmpty()) {
            return ['history' => [], 'names' => []];
        }

        $onderdeelIds = $onderdelen->pluck('id');
        $onderdeelNames = $onderdelen->pluck('naam', 'id');

        $earliestVan = DB::table('soli_relatie_onderdeel')
            ->whereIn('onderdeel_id', $onderdeelIds)
            ->min('van');

        if (! $earliestVan) {
            return ['history' => [], 'names' => $onderdelen->pluck('naam')->values()->all()];
        }

        $start = Carbon::parse($earliestVan)->startOfMonth()->toDateString();
        $end = Carbon::now()->startOfMonth()->toDateString();

        $placeholders = implode(',', array_fill(0, count($onderdeelIds), '?'));

        $rows = DB::select("
            WITH RECURSIVE months AS (
                SELECT ? AS month_start
                UNION ALL
                SELECT DATE_ADD(month_start, INTERVAL 1 MONTH)
                FROM months
                WHERE month_start < ?
            )
            SELECT
                DATE_FORMAT(m.month_start, '%Y-%m') AS month,
                r.onderdeel_id,
                COUNT(*) AS cnt
            FROM months m
            INNER JOIN soli_relatie_onderdeel r
                ON r.van <= LAST_DAY(m.month_start)
                AND (r.tot IS NULL OR r.tot >= m.month_start)
                AND r.onderdeel_id IN ({$placeholders})
            GROUP BY m.month_start, r.onderdeel_id
            ORDER BY m.month_start
        ", [$start, $end, ...$onderdeelIds]);

        // Index results by month → onderdeel_id → count
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->month][$row->onderdeel_id] = (int) $row->cnt;
        }

        // Build history with all months and all onderdelen
        $history = [];
        $current = Carbon::parse($start);
        $endDate = Carbon::parse($end);

        while ($current->lte($endDate)) {
            $month = $current->format('Y-m');
            $row = ['month' => $month];

            foreach ($onderdelen as $onderdeel) {
                $row[$onderdeel->naam] = $counts[$month][$onderdeel->id] ?? 0;
            }

            $history[] = $row;
            $current->addMonth();
        }

        return [
            'history' => $history,
            'names' => $onderdelen->pluck('naam')->values()->all(),
        ];
    }

    private function statisticsDashboard(): Response
    {
        $actieveLeden = Relatie::actief()->ofType('lid')->count();
        $donateurs = Relatie::actief()->ofType('donateur')->count();
        $instrumentenInGebruik = Instrument::inGebruik()->count();
        $openstaandeReparaties = Instrument::inReparatie()->count();

        $twelveMonthsAgo = now()->subYear()->toDateString();
        $lidTypeId = DB::table('soli_relatie_types')->where('naam', 'lid')->value('id');

        $ledenJoined = 0;
        $ledenLeft = 0;
        if ($lidTypeId) {
            $ledenJoined = DB::table('soli_relatie_relatie_type')
                ->where('relatie_type_id', $lidTypeId)
                ->where('van', '>=', $twelveMonthsAgo)
                ->count();

            $ledenLeft = DB::table('soli_relatie_relatie_type')
                ->where('relatie_type_id', $lidTypeId)
                ->where('tot', '>=', $twelveMonthsAgo)
                ->whereNotNull('tot')
                ->count();
        }

        $data = [
            'stats' => [
                'actieve_leden' => $actieveLeden,
                'donateurs' => $donateurs,
                'instrumenten_in_gebruik' => $instrumentenInGebruik,
                'openstaande_reparaties' => $openstaandeReparaties,
                'leden_joined_12m' => $ledenJoined,
                'leden_left_12m' => $ledenLeft,
            ],
        ];

        if (auth()->user()->hasRole('admin')) {
            $data['alerts'] = [
                'unlinked_users' => User::whereDoesntHave('relaties')->count(),
                'unlinked_relaties' => Relatie::actief()->whereNull('user_id')->count(),
            ];
        }

        $chartData = $this->getOnderdeelHistory();
        $data['onderdeel_history'] = $chartData['history'];
        $data['onderdeel_names'] = $chartData['names'];

        return Inertia::render('dashboard', $data);
    }
}
