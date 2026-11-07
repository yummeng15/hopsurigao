<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $fillable = [
        'package_id',
        'description',
    ];

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }
}