<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OauthClientUserRole extends Model
{
    protected $table = 'soli_oauth_client_user_roles';

    protected $fillable = ['client_setting_id', 'user_id', 'mapped_role'];

    public function clientSetting(): BelongsTo
    {
        return $this->belongsTo(OauthClientSetting::class, 'client_setting_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
