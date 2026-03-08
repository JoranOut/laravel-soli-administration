<?php

namespace App\Models;

use App\Concerns\HasDateRange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InstrumentBespeler extends Model
{
    use HasDateRange;

    protected $table = 'soli_instrument_bespelers';

    protected $appends = ['is_actueel'];

    protected $fillable = [
        'instrument_id',
        'relatie_id',
        'van',
        'tot',
    ];

    protected function casts(): array
    {
        return [
            'van' => 'date',
            'tot' => 'date',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }
}
