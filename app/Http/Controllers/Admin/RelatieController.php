<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRelatieRequest;
use App\Http\Requests\UpdateRelatieRequest;
use App\Models\Onderdeel;
use App\Models\Relatie;
use App\Models\RelatieType;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RelatieController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = auth()->user();

        if ($user->hasRole('member')) {
            if ($relatie = $user->relaties->first()) {
                return redirect()->route('admin.relaties.show', $relatie);
            }

            return Inertia::render('admin/relaties/not-linked');
        }

        $allowedSorts = ['achternaam', 'voornaam', 'relatie_nummer'];
        $sort = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'achternaam';
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';

        $relaties = Relatie::query()
            ->search($request->input('search'))
            ->ofType($request->input('type'))
            ->when(! $request->boolean('show_inactive'), fn ($q) => $q->actief())
            ->with([
                'types' => fn ($q) => $q->wherePivotNull('tot')->orWherePivot('tot', '>=', now()->toDateString()),
                'emails',
            ])
            ->orderBy($sort, $direction)
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/relaties/index', [
            'relaties' => $relaties,
            'filters' => $request->only(['search', 'type', 'show_inactive', 'sort', 'direction']),
            'relatieTypes' => RelatieType::all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $preselectedTypeId = null;

        if ($request->has('type')) {
            $type = RelatieType::where('naam', $request->input('type'))->first();
            $preselectedTypeId = $type?->id;
        }

        return Inertia::render('admin/relaties/create', [
            'relatieTypes' => RelatieType::all(),
            'nextRelatieNummer' => (Relatie::withTrashed()->max('relatie_nummer') ?? 999) + 1,
            'onderdelen' => Onderdeel::actief()->orderBy('naam')->get(),
            'preselectedTypeId' => $preselectedTypeId,
        ]);
    }

    public function store(StoreRelatieRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $baseFields = [
            'relatie_nummer', 'voornaam', 'tussenvoegsel', 'achternaam',
            'geslacht', 'geboortedatum', 'actief', 'geboorteplaats', 'nationaliteit',
        ];

        $relatie = DB::transaction(function () use ($validated, $baseFields) {
            $relatie = Relatie::create(Arr::only($validated, $baseFields));

            // Types (pivot)
            foreach ($validated['types'] ?? [] as $type) {
                $relatie->types()->attach($type['type_id'], [
                    'van' => $type['van'],
                    'tot' => $type['tot'] ?? null,
                    'functie' => $type['functie'] ?? null,
                    'email' => $type['email'] ?? null,
                ]);
            }

            // Adressen
            foreach ($validated['adressen'] ?? [] as $adres) {
                $relatie->adressen()->create($adres);
            }

            // Emails
            foreach ($validated['emails'] as $email) {
                $relatie->emails()->create($email);
            }

            // Create linked User account
            $firstEmail = $validated['emails'][0]['email'];
            $name = collect([$validated['voornaam'], $validated['tussenvoegsel'] ?? null, $validated['achternaam']])
                ->filter()->implode(' ');
            $user = User::create([
                'name' => $name,
                'email' => $firstEmail,
                'password' => Str::random(32),
            ]);
            $user->assignRole('member');
            $relatie->user_id = $user->id;
            $relatie->save();

            // Telefoons
            foreach ($validated['telefoons'] ?? [] as $telefoon) {
                $relatie->telefoons()->create($telefoon);
            }

            // Giro gegevens
            foreach ($validated['giro_gegevens'] ?? [] as $giroGegeven) {
                $relatie->giroGegevens()->create($giroGegeven);
            }

            // Lidmaatschappen
            foreach ($validated['lidmaatschappen'] ?? [] as $lidmaatschap) {
                $relatie->relatieSinds()->create($lidmaatschap);
            }

            // Onderdelen (pivot)
            foreach ($validated['onderdelen'] ?? [] as $onderdeel) {
                $relatie->onderdelen()->attach($onderdeel['onderdeel_id'], [
                    'functie' => $onderdeel['functie'] ?? null,
                    'van' => $onderdeel['van'],
                    'tot' => $onderdeel['tot'] ?? null,
                ]);
            }

            // Opleidingen
            foreach ($validated['opleidingen'] ?? [] as $opleiding) {
                $relatie->opleidingen()->create($opleiding);
            }

            return $relatie;
        });

        return redirect()
            ->route('admin.relaties.show', $relatie)
            ->with('success', __('Relation and user account created.'));
    }

    public function show(Relatie $relatie): Response
    {
        $user = auth()->user();

        if ($user->hasRole('member') && $relatie->user_id !== $user->id) {
            abort(403);
        }

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

        if ($relatie->user) {
            $relatie->user->loadCount('relaties');
        }

        $props = [
            'relatie' => $relatie,
            'relatieTypes' => RelatieType::all(),
            'onderdelen' => Onderdeel::actief()->orderBy('naam')->get(),
        ];

        if ($user->can('users.edit')) {
            $props['users'] = User::orderBy('name')->get(['id', 'name', 'email']);
        }

        if ($user->hasRole('member') && $relatie->user_id === $user->id) {
            $props['userRelaties'] = $user->relaties()
                ->orderBy('achternaam')
                ->get(['id', 'voornaam', 'tussenvoegsel', 'achternaam', 'relatie_nummer']);
        }

        return Inertia::render('admin/relaties/show', $props);
    }

    public function update(UpdateRelatieRequest $request, Relatie $relatie): RedirectResponse
    {
        $wasActief = $relatie->actief;
        $relatie->update($request->validated());

        // Auto-delete linked user account when relatie is set to inactive
        if ($wasActief && ! $relatie->actief && $relatie->user_id) {
            $relatie->user()->delete();
            $relatie->user_id = null;
            $relatie->save();
        }

        return redirect()
            ->back()
            ->with('success', __('Relation updated.'));
    }

    public function destroy(Relatie $relatie): RedirectResponse
    {
        $relatie->delete();

        return redirect()
            ->route('admin.relaties.index')
            ->with('success', __('Relation deleted.'));
    }

    public function updateAccountEmail(Request $request, Relatie $relatie): RedirectResponse
    {
        if (! $relatie->user_id) {
            return redirect()
                ->back()
                ->with('error', __('No linked user account.'));
        }

        $relatieEmailAddresses = $relatie->emails()->pluck('email')->toArray();

        $request->validate([
            'email' => [
                'required',
                'email',
                function (string $attribute, mixed $value, \Closure $fail) use ($relatieEmailAddresses) {
                    if (! in_array($value, $relatieEmailAddresses)) {
                        $fail(__('The selected email is not one of this relation\'s email addresses.'));
                    }
                },
                'unique:users,email,'.$relatie->user->id,
            ],
        ]);

        $relatie->user->email = $request->input('email');
        $relatie->user->email_verified_at = null;
        $relatie->user->save();

        return redirect()
            ->back()
            ->with('success', __('Login email updated.'));
    }

    public function storeAccount(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        $relatie->user_id = $user->id;
        $relatie->save();

        // Ensure the user's email exists in the relatie's email records
        if (! $relatie->emails()->where('email', $user->email)->exists()) {
            $relatie->emails()->create(['email' => $user->email]);
        }

        return redirect()
            ->back()
            ->with('success', __('Account linked.'));
    }

    public function resetPassword(Request $request, Relatie $relatie): RedirectResponse
    {
        if (! $relatie->user_id) {
            return redirect()
                ->back()
                ->with('error', __('No linked user account.'));
        }

        $validated = $request->validate([
            'password' => ['required', 'string', Password::default()],
        ]);

        $relatie->user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->back()
            ->with('success', __('Password has been reset.'));
    }

    public function destroyAccount(Relatie $relatie): RedirectResponse
    {
        if (! $relatie->user_id) {
            return redirect()
                ->back()
                ->with('error', __('No linked user account.'));
        }

        $otherRelatiesCount = Relatie::where('user_id', $relatie->user_id)
            ->where('id', '!=', $relatie->id)
            ->count();

        if ($otherRelatiesCount > 0) {
            // User is linked to other relaties — just disconnect
            $relatie->user_id = null;
            $relatie->save();

            return redirect()
                ->back()
                ->with('success', __('Account disconnected.'));
        }

        // User is only linked to this relatie — delete the account
        $relatie->user()->delete();
        $relatie->user_id = null;
        $relatie->save();

        return redirect()
            ->back()
            ->with('success', __('Account deleted.'));
    }
}
