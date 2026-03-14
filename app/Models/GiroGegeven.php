<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiroGegeven extends Model
{
    use HasFactory;

    protected $table = 'soli_giro_gegevens';

    protected $fillable = [
        'relatie_id',
        'iban',
        'bic',
        'tenaamstelling',
        'machtiging',
    ];

    protected function casts(): array
    {
        return [
            'machtiging' => 'boolean',
        ];
    }

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }
}
