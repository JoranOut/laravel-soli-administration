<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

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
