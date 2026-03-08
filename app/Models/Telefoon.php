<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Telefoon extends Model
{
    protected $table = 'soli_telefoons';

    protected $fillable = [
        'relatie_id',
        'nummer',
    ];

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }
}
