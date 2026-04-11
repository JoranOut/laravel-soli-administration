<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GoogleContactSyncLog;
use App\Services\Google\GoogleContactSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class GoogleContactSyncController extends Controller
{
    public function index(): Response
    {
        $logs = GoogleContactSyncLog::with('relatie:id,voornaam,tussenvoegsel,achternaam')
            ->latest('started_at')
            ->paginate(25);

        return Inertia::render('admin/google-contacts-sync', [
            'logs' => $logs,
        ]);
    }

    public function store(): RedirectResponse
    {
        dispatch(function () {
            try {
                App::make(GoogleContactSyncService::class)->syncAll();
            } catch (\Throwable) {
                // syncAll() already logs the failure
            }
        })->afterResponse();

        return back();
    }
}
