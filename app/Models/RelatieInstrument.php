<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelatieInstrument extends Model
{
    protected $table = 'soli_relatie_instrument';

    protected $fillable = [
        'relatie_id',
        'onderdeel_id',
        'instrument_soort',
    ];

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }

    public function onderdeel(): BelongsTo
    {
        return $this->belongsTo(Onderdeel::class);
    }
}
