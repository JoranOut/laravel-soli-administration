<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRoleMapping extends Model
{
    protected $table = 'soli_client_role_mappings';

    protected $fillable = ['client_setting_id', 'relatie_type_id', 'mapped_role', 'priority'];

    public function clientSetting(): BelongsTo
    {
        return $this->belongsTo(OauthClientSetting::class, 'client_setting_id');
    }

    public function relatieType(): BelongsTo
    {
        return $this->belongsTo(RelatieType::class);
    }
}
