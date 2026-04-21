<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstrumentFamilie extends Model
{
    protected $table = 'soli_instrument_families';

    protected $fillable = [
        'naam',
    ];

    public function instrumentSoorten(): HasMany
    {
        return $this->hasMany(InstrumentSoort::class);
    }
}
