<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Relatie;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserRelatieLinkController extends Controller
{
    public function index(): Response
    {
        $unlinkedUsers = User::whereDoesntHave('relatie')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $unlinkedRelaties = Relatie::actief()
            ->whereNull('user_id')
            ->orderBy('achternaam')
            ->get(['id', 'relatie_nummer', 'voornaam', 'tussenvoegsel', 'achternaam']);

        return Inertia::render('admin/koppelingen', [
            'unlinkedUsers' => $unlinkedUsers,
            'unlinkedRelaties' => $unlinkedRelaties,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'relatie_id' => ['required', 'integer', 'exists:soli_relaties,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $relatie = Relatie::findOrFail($validated['relatie_id']);

        if ($user->relatie()->exists()) {
            return back()->withErrors(['user_id' => __('This user is already linked to a relation.')]);
        }

        if ($relatie->user_id !== null) {
            return back()->withErrors(['relatie_id' => __('This relation is already linked to a user.')]);
        }

        $relatie->update(['user_id' => $user->id]);

        return back();
    }

    public function destroy(Relatie $relatie): RedirectResponse
    {
        $relatie->update(['user_id' => null]);

        return back();
    }
}
