<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RelatieType extends Model
{
    use HasFactory;

    protected $table = 'soli_relatie_types';

    protected $fillable = ['naam', 'onderdeel_koppelbaar'];

    protected function casts(): array
    {
        return [
            'onderdeel_koppelbaar' => 'boolean',
        ];
    }

    public function relaties(): BelongsToMany
    {
        return $this->belongsToMany(Relatie::class, 'soli_relatie_relatie_type')
            ->withPivot(['van', 'tot', 'onderdeel_id'])
            ->withTimestamps();
    }
}
