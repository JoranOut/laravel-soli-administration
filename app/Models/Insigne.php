<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Insigne extends Model
{
    protected $table = 'soli_insignes';

    protected $fillable = [
        'relatie_id',
        'naam',
        'datum',
    ];

    protected function casts(): array
    {
        return [
            'datum' => 'date',
        ];
    }

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }
}
