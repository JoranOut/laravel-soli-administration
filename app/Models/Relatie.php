<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Relatie extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'soli_relaties';

    protected $appends = ['volledige_naam'];

    protected $fillable = [
        'relatie_nummer',
        'voornaam',
        'tussenvoegsel',
        'achternaam',
        'geslacht',
        'geboortedatum',
        'actief',
        'beheerd_in_admin',
        'foto_url',
        'geboorteplaats',
        'nationaliteit',
    ];

    protected function casts(): array
    {
        return [
            'geboortedatum' => 'date',
            'actief' => 'boolean',
            'beheerd_in_admin' => 'boolean',
        ];
    }

    public function getVolledigeNaamAttribute(): string
    {
        return collect([$this->voornaam, $this->tussenvoegsel, $this->achternaam])
            ->filter()
            ->implode(' ');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['voornaam', 'tussenvoegsel', 'achternaam', 'geslacht', 'geboortedatum', 'actief', 'user_id'])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn (string $eventName) => "Relatie {$this->volledige_naam} {$eventName}");
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function types(): BelongsToMany
    {
        return $this->belongsToMany(RelatieType::class, 'soli_relatie_relatie_type')
            ->withPivot(['id', 'van', 'tot', 'functie', 'email', 'onderdeel_id'])
            ->withTimestamps();
    }

    public function adressen(): HasMany
    {
        return $this->hasMany(Adres::class);
    }

    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    public function telefoons(): HasMany
    {
        return $this->hasMany(Telefoon::class);
    }

    public function giroGegevens(): HasMany
    {
        return $this->hasMany(GiroGegeven::class);
    }

    public function relatieSinds(): HasMany
    {
        return $this->hasMany(RelatieSinds::class);
    }

    public function onderdelen(): BelongsToMany
    {
        return $this->belongsToMany(Onderdeel::class, 'soli_relatie_onderdeel')
            ->withPivot(['id', 'functie', 'van', 'tot'])
            ->withTimestamps();
    }

    public function relatieInstrumenten(): HasMany
    {
        return $this->hasMany(RelatieInstrument::class);
    }

    public function instrumentBespelers(): HasMany
    {
        return $this->hasMany(InstrumentBespeler::class);
    }

    public function opleidingen(): HasMany
    {
        return $this->hasMany(Opleiding::class);
    }

    public function uniformen(): HasMany
    {
        return $this->hasMany(Uniform::class);
    }

    public function insignes(): HasMany
    {
        return $this->hasMany(Insigne::class);
    }

    public function diplomas(): HasMany
    {
        return $this->hasMany(Diploma::class);
    }

    public function andereVerenigingen(): HasMany
    {
        return $this->hasMany(AndereVereniging::class);
    }

    public function scopeActief($query)
    {
        return $query->where('actief', true);
    }

    public function scopeSearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('voornaam', 'like', "%{$search}%")
                ->orWhere('achternaam', 'like', "%{$search}%")
                ->orWhere('relatie_nummer', 'like', "%{$search}%");
        });
    }

    public function scopeOfType($query, ?string $type)
    {
        if (! $type) {
            return $query;
        }

        return $query->whereHas('types', function ($q) use ($type) {
            $q->where('naam', $type)
                ->where('soli_relatie_relatie_type.van', '<=', now()->toDateString())
                ->where(function ($q2) {
                    $q2->whereNull('soli_relatie_relatie_type.tot')
                        ->orWhere('soli_relatie_relatie_type.tot', '>=', now()->toDateString());
                });
        });
    }
}
