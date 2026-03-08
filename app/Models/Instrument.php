<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Instrument extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'soli_instrumenten';

    protected $fillable = [
        'nummer',
        'soort',
        'merk',
        'model',
        'serienummer',
        'status',
        'eigendom',
        'aanschafjaar',
        'prijs',
        'locatie',
    ];

    protected function casts(): array
    {
        return [
            'prijs' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nummer', 'soort', 'status', 'eigendom'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Instrument {$this->nummer} {$eventName}");
    }

    public function bespelers(): HasMany
    {
        return $this->hasMany(InstrumentBespeler::class);
    }

    public function huidigeBespeler(): HasOne
    {
        return $this->hasOne(InstrumentBespeler::class)->whereNull('tot');
    }

    public function bijzonderheden(): HasMany
    {
        return $this->hasMany(InstrumentBijzonderheid::class);
    }

    public function reparaties(): HasMany
    {
        return $this->hasMany(InstrumentReparatie::class);
    }

    public function scopeBeschikbaar($query)
    {
        return $query->where('status', 'beschikbaar');
    }

    public function scopeInGebruik($query)
    {
        return $query->where('status', 'in_gebruik');
    }

    public function scopeInReparatie($query)
    {
        return $query->where('status', 'in_reparatie');
    }
}
