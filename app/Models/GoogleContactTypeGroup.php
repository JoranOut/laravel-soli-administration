<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleContactTypeGroup extends Model
{
    protected $table = 'soli_google_contact_type_groups';

    protected $fillable = [
        'relatie_type_id',
        'google_user_email',
        'google_resource_name',
    ];

    public function relatieType(): BelongsTo
    {
        return $this->belongsTo(RelatieType::class);
    }
}
