<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contributie extends Model
{
    use HasFactory;

    protected $table = 'soli_contributies';

    protected $fillable = [
        'tariefgroep_id',
        'soort_contributie_id',
        'jaar',
        'bedrag',
    ];

    protected function casts(): array
    {
        return [
            'bedrag' => 'decimal:2',
        ];
    }

    public function tariefgroep(): BelongsTo
    {
        return $this->belongsTo(Tariefgroep::class);
    }

    public function soortContributie(): BelongsTo
    {
        return $this->belongsTo(SoortContributie::class);
    }

    public function teBetakenContributies(): HasMany
    {
        return $this->hasMany(TeBetakenContributie::class);
    }
}
