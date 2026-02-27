<?php
// ================================================================
// WeddingDate.php — Eloquent Model
// ================================================================
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class WeddingDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fiance_nom_complet',
        'fiancee_nom_complet',
        'budget_total',
        'wedding_date',
    ];

    protected $casts = [
        'budget_total' => 'decimal:2',
        'wedding_date' => 'date',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(WeddingSponsor::class, 'wedding_dates_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(SponsorComment::class, 'wedding_dates_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'wedding_dates_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(SponsorActivityLog::class, 'wedding_dates_id');
    }

    // ─── Accessors ────────────────────────────────────────────────

    public function getDaysUntilWeddingAttribute(): int
    {
        return (int) max(0, now()->startOfDay()->diffInDays($this->wedding_date, false));
    }

    public function getCountdownAttribute(): array
    {
        $target = Carbon::parse($this->wedding_date);
        $now    = now();

        if ($target->isPast()) {
            return ['days' => 0, 'hours' => 0, 'minutes' => 0, 'seconds' => 0, 'passed' => true];
        }

        $diff = $now->diff($target);

        return [
            'days'    => $diff->days,
            'hours'   => $diff->h,
            'minutes' => $diff->i,
            'seconds' => $diff->s,
            'passed'  => false,
        ];
    }

    public function getWeddingStatsAttribute(): array
    {
        $expenses   = Expense::statsForUser($this->user_id);
        $budget     = (float) $this->budget_total;
        $total      = $expenses['grand_total'];
        $remaining  = $budget - $total;
        $percentage = $budget > 0 ? round(($total / $budget) * 100, 2) : 0;

        return array_merge($expenses, [
            'budget_total'      => $budget,
            'budget_remaining'  => $remaining,
            'budget_percentage' => $percentage,
        ]);
    }
}
