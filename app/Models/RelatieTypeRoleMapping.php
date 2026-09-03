<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class RelatieTypeRoleMapping extends Model
{
    protected $table = 'soli_relatie_type_role_mappings';

    protected $fillable = ['relatie_type_id', 'role_id'];

    public function relatieType(): BelongsTo
    {
        return $this->belongsTo(RelatieType::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
