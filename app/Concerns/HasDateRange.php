<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasDateRange
{
    public function scopeActueel(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('tot')->orWhere('tot', '>=', now()->toDateString());
        });
    }

    public function getIsActueelAttribute(): bool
    {
        return $this->tot === null || $this->tot >= now()->toDateString();
    }
}
