<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentReparatie extends Model
{
    protected $table = 'soli_instrument_reparaties';

    protected $fillable = [
        'instrument_id',
        'beschrijving',
        'reparateur',
        'kosten',
        'datum_in',
        'datum_uit',
    ];

    protected function casts(): array
    {
        return [
            'kosten' => 'decimal:2',
            'datum_in' => 'date:Y-m-d',
            'datum_uit' => 'date:Y-m-d',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
