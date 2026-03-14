<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SoortContributie extends Model
{
    use HasFactory;

    protected $table = 'soli_soort_contributies';

    protected $fillable = [
        'naam',
        'beschrijving',
    ];

    public function contributies(): HasMany
    {
        return $this->hasMany(Contributie::class);
    }
}
