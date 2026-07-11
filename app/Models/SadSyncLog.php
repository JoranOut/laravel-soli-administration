<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SadSyncLog extends Model
{
    protected $table = 'soli_sad_sync_logs';

    protected $fillable = [
        'status',
        'total',
        'created',
        'updated',
        'skipped',
        'failed',
        'deactivated',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
