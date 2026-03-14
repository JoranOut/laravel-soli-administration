<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Opleiding extends Model
{
    use HasFactory;

    protected $table = 'soli_opleidingen';

    protected $fillable = [
        'relatie_id',
        'naam',
        'instituut',
        'instrument',
        'diploma',
        'datum_start',
        'datum_einde',
        'opmerking',
    ];

    protected function casts(): array
    {
        return [
            'datum_start' => 'date',
            'datum_einde' => 'date',
        ];
    }

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }
}
