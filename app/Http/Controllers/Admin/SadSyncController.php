<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSadMembersJob;
use App\Models\SadSyncLog;
use Illuminate\Http\RedirectResponse;
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
        SyncSadMembersJob::dispatch();

        return back();
    }
}
