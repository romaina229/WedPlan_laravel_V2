<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeddingSponsor extends Model
{
    use HasFactory;

    protected $fillable = [
        'wedding_dates_id',
        'sponsor_nom_complet',
        'sponsor_conjoint_nom_complet',
        'email',
        'password_hash',
        'telephone',
        'role',
        'statut',
    ];

    protected $hidden = ['password_hash'];

    // ─── Relations ────────────────────────────────────────────────────
    public function weddingDate(): BelongsTo
    {
        return $this->belongsTo(WeddingDate::class, 'wedding_dates_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SponsorComment::class, 'sponsor_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(SponsorActivityLog::class, 'sponsor_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('statut', 'actif');
    }

    // ─── Methods ──────────────────────────────────────────────────────
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    public function logActivity(string $actionType, ?string $details = null): void
    {
        try {
            SponsorActivityLog::create([
                'sponsor_id'       => $this->id,
                'wedding_dates_id' => $this->wedding_dates_id,
                'action_type'      => $actionType,
                'details'          => $details,
                'ip_address'       => request()->ip(),
                'user_agent'       => mb_substr(request()->userAgent() ?? '', 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Silencieux
        }
    }
}
