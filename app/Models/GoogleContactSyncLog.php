<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleContactSyncLog extends Model
{
    protected $table = 'soli_google_contact_sync_logs';

    protected $fillable = [
        'type',
        'relatie_id',
        'status',
        'workspace_users',
        'contacts_created',
        'contacts_updated',
        'contacts_deleted',
        'contacts_skipped',
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

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }
}
