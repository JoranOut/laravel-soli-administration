<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(): Response
    {
        $activities = Activity::with('causer')
            ->latest()
            ->paginate(25);

        return Inertia::render('admin/activity-log', [
            'activities' => $activities,
        ]);
    }
}
