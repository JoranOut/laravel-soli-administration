<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeBetakenContributie extends Model
{
    use HasFactory;

    protected $table = 'soli_te_betalen_contributies';

    protected $fillable = [
        'relatie_id',
        'contributie_id',
        'jaar',
        'bedrag',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'bedrag' => 'decimal:2',
        ];
    }

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }

    public function contributie(): BelongsTo
    {
        return $this->belongsTo(Contributie::class);
    }

    public function betalingen(): HasMany
    {
        return $this->hasMany(Betaling::class, 'te_betalen_contributie_id');
    }
}
