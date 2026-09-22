<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adres extends Model
{
    use HasFactory;

    protected $table = 'soli_adressen';

    protected $appends = ['volledig_adres'];

    protected $fillable = [
        'relatie_id',
        'straat',
        'huisnummer',
        'huisnummer_toevoeging',
        'postcode',
        'plaats',
        'land',
    ];

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }

    public function getVolledigAdresAttribute(): string
    {
        // SAD delivers incomplete addresses, so any part may be missing — drop the
        // empty ones instead of rendering ", 1985 AA Driehuis" with a dangling comma.
        $nummer = $this->huisnummer.($this->huisnummer_toevoeging ? ' '.$this->huisnummer_toevoeging : '');
        $straat = trim("{$this->straat} {$nummer}");
        $woonplaats = trim("{$this->postcode} {$this->plaats}");

        return implode(', ', array_filter([$straat, $woonplaats]));
    }
}
