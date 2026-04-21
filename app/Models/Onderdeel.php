<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Onderdeel extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public const TYPES = ['muziekgroep', 'commissie', 'bestuur', 'staff', 'overig'];

    protected $table = 'soli_onderdelen';

    protected $fillable = [
        'naam',
        'afkorting',
        'type',
        'beschrijving',
        'actief',
    ];

    protected function casts(): array
    {
        return [
            'actief' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['naam', 'afkorting', 'type', 'beschrijving', 'actief'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Onderdeel {$this->naam} {$eventName}");
    }

    public function relaties(): BelongsToMany
    {
        return $this->belongsToMany(Relatie::class, 'soli_relatie_onderdeel')
            ->withPivot(['functie', 'van', 'tot'])
            ->withTimestamps();
    }

    public function actieveRelaties(): BelongsToMany
    {
        return $this->relaties()
            ->where(function ($q) {
                $q->whereNull('soli_relatie_onderdeel.tot')
                    ->orWhere('soli_relatie_onderdeel.tot', '>=', now()->toDateString());
            });
    }

    public function scopeActief($query)
    {
        return $query->where('actief', true);
    }
}
