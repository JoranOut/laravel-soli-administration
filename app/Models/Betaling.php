<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Betaling extends Model
{
    use HasFactory;

    protected $table = 'soli_betalingen';

    protected $fillable = [
        'te_betalen_contributie_id',
        'bedrag',
        'datum',
        'methode',
        'opmerking',
    ];

    protected function casts(): array
    {
        return [
            'bedrag' => 'decimal:2',
            'datum' => 'date',
        ];
    }

    public function teBetakenContributie(): BelongsTo
    {
        return $this->belongsTo(TeBetakenContributie::class, 'te_betalen_contributie_id');
    }
}
