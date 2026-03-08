<?php

namespace App\Models;

use App\Concerns\HasDateRange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AndereVereniging extends Model
{
    use HasDateRange;

    protected $table = 'soli_andere_verenigingen';

    protected $appends = ['is_actueel'];

    protected $fillable = [
        'relatie_id',
        'naam',
        'functie',
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

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }
}
