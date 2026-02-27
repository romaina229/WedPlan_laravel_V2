<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorComment extends Model
{
    protected $fillable = [
        'wedding_dates_id',
        'sponsor_id',
        'expense_id',
        'commentaire',
        'type_commentaire',
        'statut',
    ];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(WeddingSponsor::class, 'sponsor_id');
    }

    public function weddingDate(): BelongsTo
    {
        return $this->belongsTo(WeddingDate::class, 'wedding_dates_id');
    }
}
