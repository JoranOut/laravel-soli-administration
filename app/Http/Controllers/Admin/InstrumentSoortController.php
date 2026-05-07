<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstrumentFamilie;
use App\Models\InstrumentSoort;
use Inertia\Inertia;
use Inertia\Response;

class InstrumentSoortController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/instrumentsoorten/index', [
            'instrumentSoorten' => InstrumentSoort::with('instrumentFamilie')
                ->withCount('relatieInstrumenten')
                ->orderBy('instrument_familie_id')
                ->orderBy('naam')
                ->get(),
            'families' => InstrumentFamilie::orderBy('naam')->get(),
        ]);
    }
}
