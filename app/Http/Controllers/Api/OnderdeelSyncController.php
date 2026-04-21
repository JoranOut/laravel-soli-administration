<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Onderdeel;
use Illuminate\Http\JsonResponse;

class OnderdeelSyncController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'onderdelen' => Onderdeel::select('id', 'naam', 'afkorting', 'type', 'actief')
                ->orderBy('naam')
                ->get(),
        ]);
    }
}
