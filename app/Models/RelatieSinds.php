<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelatieSinds extends Model
{
    protected $table = 'soli_relatie_sinds';

    protected $fillable = [
        'relatie_id',
        'lid_sinds',
        'lid_tot',
        'reden_vertrek',
    ];

    protected function casts(): array
    {
        return [
            'lid_sinds' => 'date',
            'lid_tot' => 'date',
        ];
    }

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }
}
