<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'email',
        'password',
        'full_name',
        'role',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login'        => 'datetime',
        'password'          => 'hashed',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function weddingDate(): HasOne
    {
        return $this->hasOne(WeddingDate::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function adminLogs(): HasMany
    {
        return $this->hasMany(AdminLog::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeRegularUsers($query)
    {
        return $query->where('role', 'user');
    }

    // ─── Accessors ────────────────────────────────────────────────

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: $this->username;
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->role === 'admin';
    }

    // ─── Methods ──────────────────────────────────────────────────

    public function updateLastLogin(): void
    {
        $this->update(['last_login' => now()]);
    }

    public function getUnreadNotificationsCount(): int
    {
        return $this->notifications()->where('is_read', false)->count();
    }
}
