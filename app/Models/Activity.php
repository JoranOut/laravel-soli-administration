<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Activitylog v5 dropped the `table_name` config key, so the soli_ prefix lives here.
 */
class Activity extends SpatieActivity
{
    protected $table = 'soli_activity_log';
}
