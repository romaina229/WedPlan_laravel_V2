<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorActivityLog extends Model
{
    protected $fillable = [
        'sponsor_id',
        'wedding_dates_id',
        'action_type',
        'details',
        'ip_address',
        'user_agent',
    ];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(WeddingSponsor::class, 'sponsor_id');
    }
}
