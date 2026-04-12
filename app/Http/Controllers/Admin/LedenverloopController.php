<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Relatie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LedenverloopController extends Controller
{
    public function index(Request $request): Response
    {
        $lidTypeId = DB::table('soli_relatie_types')->where('naam', 'lid')->value('id') ?? 0;

        $joined = Relatie::query()
            ->select('soli_relaties.*')
            ->addSelect('soli_relatie_relatie_type.van as lid_datum')
            ->join('soli_relatie_relatie_type', 'soli_relaties.id', '=', 'soli_relatie_relatie_type.relatie_id')
            ->where('soli_relatie_relatie_type.relatie_type_id', $lidTypeId)
            ->with('onderdelen')
            ->orderByDesc('soli_relatie_relatie_type.van')
            ->paginate(25, ['*'], 'joined_page')
            ->withQueryString()
            ->through(function ($relatie) {
                $minVan = $relatie->onderdelen->min('pivot.van');
                $relatie->setRelation('onderdelen', $relatie->onderdelen->filter(fn ($o) => $o->pivot->van === $minVan)->values());

                return $relatie;
            });

        $left = Relatie::query()
            ->select('soli_relaties.*')
            ->addSelect('soli_relatie_relatie_type.tot as lid_datum')
            ->join('soli_relatie_relatie_type', 'soli_relaties.id', '=', 'soli_relatie_relatie_type.relatie_id')
            ->where('soli_relatie_relatie_type.relatie_type_id', $lidTypeId)
            ->whereNotNull('soli_relatie_relatie_type.tot')
            ->with(['onderdelen' => fn ($q) => $q->whereNotNull('soli_relatie_onderdeel.tot')])
            ->orderByDesc('soli_relatie_relatie_type.tot')
            ->paginate(25, ['*'], 'left_page')
            ->withQueryString()
            ->through(function ($relatie) {
                $maxTot = $relatie->onderdelen->max('pivot.tot');
                $relatie->setRelation('onderdelen', $relatie->onderdelen->filter(fn ($o) => $o->pivot->tot === $maxTot)->values());

                return $relatie;
            });

        return Inertia::render('admin/ledenverloop/index', [
            'joined' => $joined,
            'left' => $left,
            'tab' => $request->input('tab', 'joined'),
        ]);
    }
}
