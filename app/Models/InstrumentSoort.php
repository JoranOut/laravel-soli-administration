<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentSoort extends Model
{
    protected $table = 'soli_instrument_soorten';

    protected $fillable = [
        'naam',
        'instrument_familie_id',
    ];

    public function instrumentFamilie(): BelongsTo
    {
        return $this->belongsTo(InstrumentFamilie::class);
    }

    public function relatieInstrumenten(): HasMany
    {
        return $this->hasMany(RelatieInstrument::class);
    }
}
