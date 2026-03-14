<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Email extends Model
{
    use HasFactory;

    protected $table = 'soli_emails';

    protected $fillable = [
        'relatie_id',
        'email',
    ];

    public function relatie(): BelongsTo
    {
        return $this->belongsTo(Relatie::class);
    }
}
