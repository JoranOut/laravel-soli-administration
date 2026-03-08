<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tariefgroep extends Model
{
    protected $table = 'soli_tariefgroepen';

    protected $fillable = [
        'naam',
        'beschrijving',
    ];

    public function contributies(): HasMany
    {
        return $this->hasMany(Contributie::class);
    }
}
