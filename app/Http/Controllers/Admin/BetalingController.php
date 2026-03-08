<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Betaling;
use App\Models\TeBetakenContributie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BetalingController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->input('status', 'open');

        $openstaand = TeBetakenContributie::with([
            'relatie',
            'contributie.soortContributie',
            'contributie.tariefgroep',
            'betalingen',
        ])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('jaar')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/financieel/betalingen', [
            'openstaand' => $openstaand,
            'filters' => ['status' => $status],
        ]);
    }

    public function store(Request $request, TeBetakenContributie $teBetakenContributie): RedirectResponse
    {
        $validated = $request->validate([
            'bedrag' => ['required', 'numeric', 'min:0.01'],
            'datum' => ['required', 'date'],
            'methode' => ['nullable', 'string', 'max:255'],
            'opmerking' => ['nullable', 'string'],
        ]);

        $teBetakenContributie->betalingen()->create($validated);

        // Check if fully paid
        $totalBetaald = $teBetakenContributie->betalingen()->sum('bedrag');
        if ($totalBetaald >= $teBetakenContributie->bedrag) {
            $teBetakenContributie->update(['status' => 'betaald']);
        }

        return back()->with('success', __('Payment registered.'));
    }
}
