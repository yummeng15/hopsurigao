<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourTime extends Model
{
    protected $table = 'times';

    protected $fillable = [
        'tour_date_id',
        'time',
        'description',
    ];

    public function tourDate(): BelongsTo
    {
        return $this->belongsTo(TourDate::class);
    }
}