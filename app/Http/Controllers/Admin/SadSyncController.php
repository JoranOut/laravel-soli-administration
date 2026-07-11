<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SadSyncLog;
use App\Services\Sad\SadSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class SadSyncController extends Controller
{
    public function index(): Response
    {
        $logs = SadSyncLog::latest('started_at')->paginate(25);

        return Inertia::render('admin/sad-sync', [
            'logs' => $logs,
        ]);
    }

    public function store(): RedirectResponse
    {
        dispatch(function () {
            try {
                App::make(SadSyncService::class)->syncAll();
            } catch (\Throwable) {
                // syncAll() already logs the failure
            }
        })->afterResponse();

        return back();
    }
}
