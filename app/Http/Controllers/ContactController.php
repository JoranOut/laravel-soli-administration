<?php

namespace App\Http\Controllers;

use App\Models\Relatie;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(): Response
    {
        $bestuur = Relatie::actief()
            ->ofType('bestuur')
            ->with(['types' => fn ($q) => $q->where('naam', 'bestuur')])
            ->orderBy('achternaam')
            ->get()
            ->map(function (Relatie $relatie) {
                $bestuurType = $relatie->types->firstWhere('naam', 'bestuur');

                return [
                    'id' => $relatie->id,
                    'volledige_naam' => $relatie->volledige_naam,
                    'functie' => $bestuurType?->pivot?->functie,
                    'email' => $bestuurType?->pivot?->email,
                ];
            });

        $contactpersonen = Relatie::actief()
            ->ofType('contactpersoon')
            ->with(['types' => fn ($q) => $q->where('naam', 'contactpersoon')])
            ->orderBy('achternaam')
            ->get()
            ->map(function (Relatie $relatie) {
                $contactType = $relatie->types->firstWhere('naam', 'contactpersoon');

                return [
                    'id' => $relatie->id,
                    'volledige_naam' => $relatie->volledige_naam,
                    'functie' => $contactType?->pivot?->functie,
                    'email' => $contactType?->pivot?->email,
                ];
            });

        return Inertia::render('contact', [
            'bestuur' => $bestuur,
            'contactpersonen' => $contactpersonen,
        ]);
    }
}
