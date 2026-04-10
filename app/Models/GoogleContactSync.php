<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleContactSync extends Model
{
    protected $table = 'soli_google_contact_syncs';

    protected $fillable = [
        'relatie_id',
        'google_user_email',
        'google_resource_name',
        'data_hash',
    ];

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }
}
