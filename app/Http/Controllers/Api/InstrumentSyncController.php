<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstrumentFamilie;
use App\Models\InstrumentSoort;
use Illuminate\Http\JsonResponse;

class InstrumentSyncController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'families' => InstrumentFamilie::select('id', 'naam')->orderBy('naam')->get(),
            'soorten' => InstrumentSoort::select('id', 'naam', 'instrument_familie_id')->orderBy('naam')->get(),
        ]);
    }
}
