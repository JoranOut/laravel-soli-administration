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
        $nummer = $this->huisnummer.($this->huisnummer_toevoeging ? ' '.$this->huisnummer_toevoeging : '');

        return "{$this->straat} {$nummer}, {$this->postcode} {$this->plaats}";
    }
}
