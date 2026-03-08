<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentBijzonderheid extends Model
{
    protected $table = 'soli_instrument_bijzonderheden';

    protected $fillable = [
        'instrument_id',
        'beschrijving',
        'datum',
    ];

    protected function casts(): array
    {
        return [
            'datum' => 'date',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
