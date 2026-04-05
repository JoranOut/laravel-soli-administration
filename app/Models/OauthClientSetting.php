<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Passport\Client;

class OauthClientSetting extends Model
{
    protected $table = 'soli_oauth_client_settings';

    protected $fillable = ['client_id', 'type', 'default_role'];

    public function roleMappings(): HasMany
    {
        return $this->hasMany(ClientRoleMapping::class, 'client_setting_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
