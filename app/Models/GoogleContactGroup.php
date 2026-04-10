<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleContactGroup extends Model
{
    protected $table = 'soli_google_contact_groups';

    protected $fillable = [
        'onderdeel_id',
        'google_user_email',
        'google_resource_name',
    ];

    public function onderdeel(): BelongsTo
    {
        return $this->belongsTo(Onderdeel::class);
    }
}
