<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Relatie;
use App\Models\User;
use App\Services\DerivedRoleSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserRelatieLinkController extends Controller
{
    public function __construct(private readonly DerivedRoleSyncService $derivedRoles) {}

    public function index(): Response
    {
        $unlinkedUsers = User::whereDoesntHave('relaties')
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

        if ($relatie->user_id !== null) {
            return back()->withErrors(['relatie_id' => __('This relation is already linked to a user.')]);
        }

        $relatie->user_id = $user->id;
        $relatie->save();

        // Ensure the user's email exists in the relatie's email records
        if (! $relatie->emails()->where('email', $user->email)->exists()) {
            $relatie->emails()->create(['email' => $user->email]);
        }

        // The user inherits the relatie's types, and any role they map to.
        $this->derivedRoles->syncUser($user->load('roles'));

        return back();
    }

    public function destroy(Relatie $relatie): RedirectResponse
    {
        $user = $relatie->user;

        $relatie->user_id = null;
        $relatie->save();

        if ($user) {
            $this->derivedRoles->syncUser($user->load('roles'));
        }

        return back();
    }

    public function destroyUser(User $user): RedirectResponse
    {
        if ($user->relaties()->exists()) {
            return back()->withErrors(['user_id' => __('This user is still linked to a relation.')]);
        }

        $user->delete();

        return back()->with('success', __('User account deleted.'));
    }
}
