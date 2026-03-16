<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Adres;
use App\Models\Email;
use App\Models\GiroGegeven;
use App\Models\Relatie;
use App\Models\Telefoon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RelatieContactController extends Controller
{
    // Adressen
    public function storeAdres(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'straat' => ['required', 'string', 'max:255'],
            'huisnummer' => ['required', 'string', 'max:20'],
            'huisnummer_toevoeging' => ['nullable', 'string', 'max:20'],
            'postcode' => ['required', 'string', 'max:10'],
            'plaats' => ['required', 'string', 'max:255'],
            'land' => ['required', 'string', 'max:255'],
        ]);

        $relatie->adressen()->create($validated);

        return back()->with('success', __('Address added.'));
    }

    public function updateAdres(Request $request, Relatie $relatie, Adres $adres): RedirectResponse
    {
        abort_unless($adres->relatie_id === $relatie->id, 404);

        $validated = $request->validate([
            'straat' => ['required', 'string', 'max:255'],
            'huisnummer' => ['required', 'string', 'max:20'],
            'huisnummer_toevoeging' => ['nullable', 'string', 'max:20'],
            'postcode' => ['required', 'string', 'max:10'],
            'plaats' => ['required', 'string', 'max:255'],
            'land' => ['required', 'string', 'max:255'],
        ]);

        $adres->update($validated);

        return back()->with('success', __('Address updated.'));
    }

    public function destroyAdres(Relatie $relatie, Adres $adres): RedirectResponse
    {
        abort_unless($adres->relatie_id === $relatie->id, 404);

        $adres->delete();

        return back()->with('success', __('Address deleted.'));
    }

    // Emails
    public function storeEmail(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $relatie->emails()->create($validated);

        return back()->with('success', __('Email added.'));
    }

    public function updateEmail(Request $request, Relatie $relatie, Email $email): RedirectResponse
    {
        abort_unless($email->relatie_id === $relatie->id, 404);

        $oldEmail = $email->email;
        $isLoginEmail = $relatie->user && $relatie->user->email === $oldEmail;

        $uniqueRule = 'unique:users,email';
        if ($isLoginEmail) {
            $uniqueRule .= ',' . $relatie->user->id;
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', $uniqueRule],
        ]);

        $email->update($validated);

        if ($isLoginEmail) {
            $relatie->user->email = $validated['email'];
            $relatie->user->email_verified_at = null;
            $relatie->user->save();
        }

        return back()->with('success', __('Email updated.'));
    }

    public function destroyEmail(Relatie $relatie, Email $email): RedirectResponse
    {
        abort_unless($email->relatie_id === $relatie->id, 404);

        if ($relatie->user && $relatie->user->email === $email->email) {
            return back()->with('error', __('This email is used as the login email and cannot be deleted.'));
        }

        $email->delete();

        return back()->with('success', __('Email deleted.'));
    }

    // Telefoons
    public function storeTelefoon(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'nummer' => ['required', 'string', 'max:20'],
        ]);

        $relatie->telefoons()->create($validated);

        return back()->with('success', __('Phone number added.'));
    }

    public function updateTelefoon(Request $request, Relatie $relatie, Telefoon $telefoon): RedirectResponse
    {
        abort_unless($telefoon->relatie_id === $relatie->id, 404);

        $validated = $request->validate([
            'nummer' => ['required', 'string', 'max:20'],
        ]);

        $telefoon->update($validated);

        return back()->with('success', __('Phone number updated.'));
    }

    public function destroyTelefoon(Relatie $relatie, Telefoon $telefoon): RedirectResponse
    {
        abort_unless($telefoon->relatie_id === $relatie->id, 404);

        $telefoon->delete();

        return back()->with('success', __('Phone number deleted.'));
    }

    // Giro gegevens
    public function storeGiroGegeven(Request $request, Relatie $relatie): RedirectResponse
    {
        $validated = $request->validate([
            'iban' => ['required', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'tenaamstelling' => ['required', 'string', 'max:255'],
            'machtiging' => ['boolean'],
        ]);

        $relatie->giroGegevens()->create($validated);

        return back()->with('success', __('Bank details added.'));
    }

    public function updateGiroGegeven(Request $request, Relatie $relatie, GiroGegeven $giroGegeven): RedirectResponse
    {
        abort_unless($giroGegeven->relatie_id === $relatie->id, 404);

        $validated = $request->validate([
            'iban' => ['required', 'string', 'max:34'],
            'bic' => ['nullable', 'string', 'max:11'],
            'tenaamstelling' => ['required', 'string', 'max:255'],
            'machtiging' => ['boolean'],
        ]);

        $giroGegeven->update($validated);

        return back()->with('success', __('Bank details updated.'));
    }

    public function destroyGiroGegeven(Relatie $relatie, GiroGegeven $giroGegeven): RedirectResponse
    {
        abort_unless($giroGegeven->relatie_id === $relatie->id, 404);

        $giroGegeven->delete();

        return back()->with('success', __('Bank details deleted.'));
    }
}
