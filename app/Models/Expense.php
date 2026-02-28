<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'quantity',
        'unit_price',
        'frequency',
        'paid',
        'payment_date',
        'notes',
    ];

    protected $casts = [
        'quantity'     => 'integer',
        'unit_price'   => 'decimal:2',
        'frequency'    => 'integer',
        'paid'         => 'boolean',
        'payment_date' => 'date',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function sponsorComments(): HasMany
    {
        return $this->hasMany(SponsorComment::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('paid', true);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->where('paid', false);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCategory(Builder $query, int $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeWithFilters(Builder $query, array $filters): Builder
    {
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (isset($filters['paid']) && $filters['paid'] !== '') {
            $query->where('paid', (bool) $filters['paid']);
        }

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }

        if (!empty($filters['min_amount'])) {
            $query->whereRaw('(quantity * unit_price * frequency) >= ?', [(float) $filters['min_amount']]);
        }

        if (!empty($filters['max_amount'])) {
            $query->whereRaw('(quantity * unit_price * frequency) <= ?', [(float) $filters['max_amount']]);
        }

        return $query;
    }

    // ─── Accessors ────────────────────────────────────────────────

    public function getTotalAmountAttribute(): float
    {
        return round($this->quantity * (float) $this->unit_price * $this->frequency, 2);
    }

    public function getFormattedTotalAttribute(): string
    {
        return number_format($this->total_amount, 0, ',', ' ') . ' FCFA';
    }

    // ─── Methods ──────────────────────────────────────────────────

    public function togglePaid(): bool
    {
        $this->paid         = !$this->paid;
        $this->payment_date = $this->paid ? now()->toDateString() : null;
        return $this->save();
    }

    // ─── Static stats ─────────────────────────────────────────────

    public static function grandTotalForUser(int $userId): float
    {
        return (float) static::where('user_id', $userId)
            ->selectRaw('COALESCE(SUM(quantity * unit_price * frequency), 0) as total')
            ->value('total');
    }

    public static function paidTotalForUser(int $userId): float
    {
        return (float) static::where('user_id', $userId)
            ->where('paid', true)
            ->selectRaw('COALESCE(SUM(quantity * unit_price * frequency), 0) as total')
            ->value('total');
    }

    public static function statsForUser(int $userId): array
    {
        $grand   = static::grandTotalForUser($userId);
        $paid    = static::paidTotalForUser($userId);
        $unpaid  = $grand - $paid;
        $paidPct = $grand > 0 ? round(($paid / $grand) * 100, 2) : 0;

        $counts = static::where('user_id', $userId)
            ->selectRaw('
                COUNT(*) as total_items,
                SUM(CASE WHEN paid = true THEN 1 ELSE 0 END) as paid_items
            ')
            ->first();
        // ✅ Correction PostgreSQL : SUM(paid) ne fonctionne pas sur boolean
        // On utilise SUM(CASE WHEN paid = true THEN 1 ELSE 0 END) à la place

        return [
            'grand_total'        => $grand,
            'paid_total'         => $paid,
            'unpaid_total'       => $unpaid,
            'payment_percentage' => $paidPct,
            'total_items'        => (int) ($counts->total_items ?? 0),
            'paid_items'         => (int) ($counts->paid_items ?? 0),
            'unpaid_items'       => (int) (($counts->total_items ?? 0) - ($counts->paid_items ?? 0)),
        ];
    }

    public static function categoryStatsForUser(int $userId): array
    {
        return static::where('expenses.user_id', $userId)
            ->join('categories', 'expenses.category_id', '=', 'categories.id')
            ->selectRaw('
                categories.id,
                categories.name,
                categories.color,
                categories.icon,
                categories.display_order,
                COUNT(expenses.id) as expense_count,
                COALESCE(SUM(expenses.quantity * expenses.unit_price * expenses.frequency), 0) as total,
                COALESCE(SUM(CASE WHEN expenses.paid = true THEN expenses.quantity * expenses.unit_price * expenses.frequency ELSE 0 END), 0) as paid_total,
                COALESCE(SUM(CASE WHEN expenses.paid = false OR expenses.paid IS NULL THEN expenses.quantity * expenses.unit_price * expenses.frequency ELSE 0 END), 0) as unpaid_total,
                SUM(CASE WHEN expenses.paid = true THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN expenses.paid = false OR expenses.paid IS NULL THEN 1 ELSE 0 END) as unpaid_count
            ')
            // ✅ Correction PostgreSQL : paid != 0 remplacé par paid = true (boolean natif)
            ->groupBy('categories.id', 'categories.name', 'categories.color', 'categories.icon', 'categories.display_order')
            ->orderBy('categories.display_order')
            ->get()
            ->map(function ($row) {
                $paidTotal   = (float) $row->paid_total;
                $unpaidTotal = (float) $row->unpaid_total;
                $total       = (float) $row->total;
                return [
                    'id'              => $row->id,
                    'name'            => $row->name,
                    'color'           => $row->color,
                    'icon'            => $row->icon,
                    'display_order'   => $row->display_order,
                    'expense_count'   => (int) $row->expense_count,
                    'total'           => $total,
                    'paid'            => $paidTotal,
                    'unpaid'          => $unpaidTotal,
                    'paid_count'      => (int) $row->paid_count,
                    'unpaid_count'    => (int) $row->unpaid_count,
                    'percentage_paid' => $total > 0 ? round(($paidTotal / $total) * 100, 2) : 0,
                ];
            })
            ->toArray();
    }
}