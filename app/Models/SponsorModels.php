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

    protected $casts = [
        'statut' => 'string',
        'role'   => 'string',
    ];

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

    public function scopeActive($query)
    {
        return $query->where('statut', 'actif');
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password_hash'] = password_hash($value, PASSWORD_DEFAULT);
    }

    public function logActivity(string $actionType, ?string $details = null, ?string $ip = null): void
    {
        SponsorActivityLog::create([
            'sponsor_id'       => $this->id,
            'wedding_dates_id' => $this->wedding_dates_id,
            'action_type'      => $actionType,
            'details'          => $details,
            'ip_address'       => $ip ?? request()->ip(),
            'user_agent'       => request()->userAgent(),
        ]);
    }
}


class SponsorComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'wedding_dates_id',
        'sponsor_id',
        'expense_id',
        'commentaire',
        'type_commentaire',
        'statut',
    ];

    public function weddingDate(): BelongsTo
    {
        return $this->belongsTo(WeddingDate::class, 'wedding_dates_id');
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(WeddingSponsor::class, 'sponsor_id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function scopePublic($query)
    {
        return $query->where('statut', 'public');
    }
}


class SponsorActivityLog extends Model
{
    use HasFactory;

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

    public function weddingDate(): BelongsTo
    {
        return $this->belongsTo(WeddingDate::class, 'wedding_dates_id');
    }
}


class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wedding_dates_id',
        'type_notification',
        'message',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function weddingDate(): BelongsTo
    {
        return $this->belongsTo(WeddingDate::class, 'wedding_dates_id');
    }

    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]);
    }
}


class AdminLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'action_type',
        'details',
        'ip_address',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(
        ?int $userId,
        string $action,
        string $type = 'auth',
        ?string $details = null
    ): self {
        return static::create([
            'user_id'     => $userId,
            'action'      => $action,
            'action_type' => $type,
            'details'     => $details,
            'ip_address'  => request()->ip(),
        ]);
    }
}
