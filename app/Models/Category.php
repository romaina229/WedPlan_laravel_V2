<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
        'icon',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    // ─── Relations ────────────────────────────────────────────────

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('id');
    }

    // ─── Computed stats (for a specific user) ─────────────────────

    public function totalForUser(int $userId): float
    {
        return (float) $this->expenses()
            ->where('user_id', $userId)
            ->selectRaw('COALESCE(SUM(quantity * unit_price * frequency), 0) as total')
            ->value('total');
    }

    public function paidTotalForUser(int $userId): float
    {
        return (float) $this->expenses()
            ->where('user_id', $userId)
            ->where('paid', true)
            ->selectRaw('COALESCE(SUM(quantity * unit_price * frequency), 0) as total')
            ->value('total');
    }

    public function getStatsForUser(int $userId): array
    {
        $total = $this->totalForUser($userId);
        $paid  = $this->paidTotalForUser($userId);

        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'color'          => $this->color,
            'icon'           => $this->icon,
            'display_order'  => $this->display_order,
            'total'          => $total,
            'paid'           => $paid,
            'unpaid'         => $total - $paid,
            'percentage_paid'=> $total > 0 ? round(($paid / $total) * 100, 2) : 0,
        ];
    }
}
