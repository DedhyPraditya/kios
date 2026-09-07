<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DismissedAlert extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'alertable_id',
        'last_value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
