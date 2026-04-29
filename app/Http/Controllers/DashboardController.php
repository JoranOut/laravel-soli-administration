<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\Onderdeel;
use App\Models\Relatie;
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
            'types',
            'adressen' => fn ($q) => $q->orderByDesc('created_at'),
            'emails' => fn ($q) => $q->orderByDesc('created_at'),
            'telefoons' => fn ($q) => $q->orderByDesc('created_at'),
            'giroGegevens' => fn ($q) => $q->orderByDesc('created_at'),
            'relatieSinds' => fn ($q) => $q->orderByDesc('lid_sinds'),
            'onderdelen' => fn ($q) => $q->orderByDesc('soli_relatie_onderdeel.van'),
            'relatieInstrumenten.onderdeel',
            'relatieInstrumenten.instrumentSoort',
            'instrumentBespelers.instrument',
            'opleidingen' => fn ($q) => $q->orderByDesc('datum_start'),
            'uniformen' => fn ($q) => $q->orderByDesc('van'),
            'insignes' => fn ($q) => $q->orderByDesc('datum'),
            'diplomas' => fn ($q) => $q->orderBy('naam'),
            'andereVerenigingen' => fn ($q) => $q->orderByDesc('van'),
        ]);

        return Inertia::render('admin/relaties/show', [
            'relatie' => $relatie,
            'userRelaties' => $userRelaties,
        ]);
    }

    private function getOnderdeelHistory(): array
    {
        $onderdelen = Onderdeel::where('type', 'muziekgroep')
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

    /**
     * Normalize a plaats string for consistent comparison.
     * Strips punctuation, collapses whitespace, lowercases.
     * e.g. "SANTPOORT - ZUID" → "santpoort zuid", "VELSERBROEK ." → "velserbroek"
     */
    private function normalizePlaats(string $plaats): string
    {
        $plaats = mb_strtolower(trim($plaats));
        $plaats = preg_replace('/[^a-z\s]/', '', $plaats);

        return preg_replace('/\s+/', ' ', trim($plaats));
    }

    private function isVelsenPlaats(string $normalized): bool
    {
        $velsenPlaces = [
            'driehuis',
            'ijmuiden',
            'santpoort noord',
            'santpoort zuid',
            'santpoort',
            'velserbroek',
            'velsen noord',
            'velsen zuid',
        ];

        return in_array($normalized, $velsenPlaces);
    }

    private function getResidenceStats(): array
    {
        $activeLidIds = Relatie::actief()->ofType('lid')->pluck('soli_relaties.id');

        if ($activeLidIds->isEmpty()) {
            return ['top' => [], 'inside_velsen' => 0, 'outside_velsen' => 0];
        }

        // Get the latest address per relatie
        $latestAddresses = DB::table('soli_adressen as a')
            ->joinSub(
                DB::table('soli_adressen')
                    ->select('relatie_id', DB::raw('MAX(id) as max_id'))
                    ->whereIn('relatie_id', $activeLidIds)
                    ->groupBy('relatie_id'),
                'latest',
                fn ($join) => $join->on('a.id', '=', 'latest.max_id')
            )
            ->select('a.plaats')
            ->get();

        $grouped = $latestAddresses->groupBy(fn ($row) => $row->plaats ?: '');
        $top = $grouped->map->count()
            ->sortDesc()
            ->take(5)
            ->map(fn ($count, $plaats) => ['plaats' => $plaats, 'count' => $count])
            ->values()
            ->all();

        $insideVelsen = $latestAddresses->filter(
            fn ($row) => $this->isVelsenPlaats($this->normalizePlaats($row->plaats ?? ''))
        )->count();

        return [
            'top' => $top,
            'inside_velsen' => $insideVelsen,
            'outside_velsen' => $latestAddresses->count() - $insideVelsen,
        ];
    }

    private function getInstrumentStats(): array
    {
        $today = now()->toDateString();

        $rows = DB::table('soli_relatie_instrument as ri')
            ->join('soli_instrument_soorten as s', 'ri.instrument_soort_id', '=', 's.id')
            ->join('soli_relaties as r', 'ri.relatie_id', '=', 'r.id')
            ->join('soli_relatie_onderdeel as ro', function ($join) {
                $join->on('ro.relatie_id', '=', 'ri.relatie_id')
                    ->on('ro.onderdeel_id', '=', 'ri.onderdeel_id');
            })
            ->where('r.actief', true)
            ->where('ro.van', '<=', $today)
            ->where(fn ($q) => $q->whereNull('ro.tot')->orWhere('ro.tot', '>=', $today))
            ->select(
                's.naam',
                DB::raw('COUNT(DISTINCT ri.id) as total'),
                DB::raw('COUNT(DISTINCT CASE WHEN r.geboortedatum IS NOT NULL AND TIMESTAMPDIFF(YEAR, r.geboortedatum, CURDATE()) >= 60 THEN ri.id END) as over_60')
            )
            ->groupBy('s.naam')
            ->orderByDesc('total')
            ->get();

        return $rows->map(fn ($row) => [
            'naam' => $row->naam,
            'total' => (int) $row->total,
            'over_60' => (int) $row->over_60,
        ])->all();
    }

    private function getAgeDistribution(): array
    {
        $members = Relatie::actief()->ofType('lid')
            ->whereNotNull('soli_relaties.geboortedatum')
            ->pluck('soli_relaties.geboortedatum');

        $brackets = ['0-17' => 0, '18-29' => 0, '30-44' => 0, '45-59' => 0, '60-74' => 0, '75+' => 0];
        $totalAge = 0;

        foreach ($members as $geboortedatum) {
            $age = Carbon::parse($geboortedatum)->age;
            $totalAge += $age;

            if ($age < 18) {
                $brackets['0-17']++;
            } elseif ($age < 30) {
                $brackets['18-29']++;
            } elseif ($age < 45) {
                $brackets['30-44']++;
            } elseif ($age < 60) {
                $brackets['45-59']++;
            } elseif ($age < 75) {
                $brackets['60-74']++;
            } else {
                $brackets['75+']++;
            }
        }

        $count = $members->count();

        return [
            'brackets' => collect($brackets)->map(fn ($c, $label) => ['bracket' => $label, 'count' => $c])->values()->all(),
            'average_age' => $count > 0 ? round($totalAge / $count, 1) : null,
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
            'residence_stats' => $this->getResidenceStats(),
            'instrument_stats' => $this->getInstrumentStats(),
            'age_distribution' => $this->getAgeDistribution(),
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
