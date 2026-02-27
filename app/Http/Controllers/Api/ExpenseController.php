<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Expense;
use App\Models\AdminLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExpenseController extends Controller
{
    private const MAX_EXPENSES = 500;

    // ─── List all expenses (with filters) ─────────────────────────

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $expenses = Expense::with('category')
            ->forUser($userId)
            ->withFilters($request->only(['category_id', 'paid', 'search', 'min_amount', 'max_amount']))
            ->orderBy('category_id')
            ->orderBy('id')
            ->get()
            ->map(fn($e) => $this->formatExpense($e));

        return response()->json([
            'success'  => true,
            'data'     => $expenses,
            'count'    => $expenses->count(),
        ]);
    }

    // ─── Get single expense ───────────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $expense = Expense::with('category')
            ->forUser($request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatExpense($expense),
        ]);
    }

    // ─── Create expense ───────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Check limit
        $count = Expense::where('user_id', $userId)->count();
        if ($count >= self::MAX_EXPENSES) {
            return response()->json([
                'success' => false,
                'message' => "Limite de " . self::MAX_EXPENSES . " dépenses atteinte.",
            ], 429);
        }

        $validator = Validator::make($request->all(), [
            'category_id'    => 'required_without:new_category|integer|exists:categories,id',
            'new_category'   => 'required_without:category_id|string|max:100',
            'name'           => 'required|string|max:255',
            'quantity'       => 'required|integer|min:1',
            'unit_price'     => 'required|numeric|min:0',
            'frequency'      => 'required|integer|min:1',
            'paid'           => 'boolean',
            'payment_date'   => 'nullable|date',
            'notes'          => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        // Create category on-the-fly
        if ($request->filled('new_category') && !$request->filled('category_id')) {
            $cat = Category::firstOrCreate(
                ['name' => trim($request->new_category)],
                [
                    'color'         => '#8b4f8d',
                    'icon'          => 'fas fa-folder',
                    'display_order' => Category::max('display_order') + 1,
                ]
            );
            $categoryId = $cat->id;
        } else {
            $categoryId = $request->category_id;
        }

        $expense = Expense::create([
            'user_id'      => $userId,
            'category_id'  => $categoryId,
            'name'         => trim($request->name),
            'quantity'     => $request->quantity,
            'unit_price'   => $request->unit_price,
            'frequency'    => $request->frequency,
            'paid'         => $request->boolean('paid'),
            'payment_date' => $request->payment_date,
            'notes'        => $request->notes,
        ]);

        AdminLog::log($userId, "Ajout dépense: {$expense->name}", 'data');

        return response()->json([
            'success' => true,
            'message' => 'Dépense ajoutée avec succès.',
            'data'    => $this->formatExpense($expense->load('category')),
        ], 201);
    }

    // ─── Update expense ───────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $userId  = $request->user()->id;
        $expense = Expense::forUser($userId)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'category_id'  => 'required|integer|exists:categories,id',
            'name'         => 'required|string|max:255',
            'quantity'     => 'required|integer|min:1',
            'unit_price'   => 'required|numeric|min:0',
            'frequency'    => 'required|integer|min:1',
            'paid'         => 'boolean',
            'payment_date' => 'nullable|date',
            'notes'        => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $expense->update([
            'category_id'  => $request->category_id,
            'name'         => trim($request->name),
            'quantity'     => $request->quantity,
            'unit_price'   => $request->unit_price,
            'frequency'    => $request->frequency,
            'paid'         => $request->boolean('paid'),
            'payment_date' => $request->payment_date,
            'notes'        => $request->notes,
        ]);

        AdminLog::log($userId, "Mise à jour dépense: {$expense->name}", 'data');

        return response()->json([
            'success' => true,
            'message' => 'Dépense mise à jour avec succès.',
            'data'    => $this->formatExpense($expense->fresh('category')),
        ]);
    }

    // ─── Delete expense ───────────────────────────────────────────

    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId  = $request->user()->id;
        $expense = Expense::forUser($userId)->findOrFail($id);
        $name    = $expense->name;

        $expense->delete();
        AdminLog::log($userId, "Suppression dépense: {$name}", 'data');

        return response()->json([
            'success' => true,
            'message' => 'Dépense supprimée avec succès.',
        ]);
    }

    // ─── Toggle paid ──────────────────────────────────────────────

    public function togglePaid(Request $request, int $id): JsonResponse
    {
        $expense = Expense::forUser($request->user()->id)->findOrFail($id);
        $expense->togglePaid();

        return response()->json([
            'success' => true,
            'message' => $expense->paid ? 'Marqué comme payé.' : 'Marqué comme non payé.',
            'data'    => $this->formatExpense($expense->load('category')),
        ]);
    }

    // ─── Statistics ───────────────────────────────────────────────

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'success' => true,
            'data'    => Expense::statsForUser($userId),
        ]);
    }

    public function categoryStats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        return response()->json([
            'success' => true,
            'data'    => Expense::categoryStatsForUser($userId),
        ]);
    }

    // ─── Bulk actions ─────────────────────────────────────────────

    public function bulkMarkPaid(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $ids    = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json(['success' => false, 'message' => 'Aucun ID fourni.'], 422);
        }

        Expense::forUser($userId)->whereIn('id', $ids)->update([
            'paid'         => true,
            'payment_date' => now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'message' => count($ids) . ' dépense(s) marquée(s) comme payée(s).',
        ]);
    }

    // ─── Format helper ────────────────────────────────────────────

    private function formatExpense(Expense $expense): array
    {
        return [
            'id'             => $expense->id,
            'user_id'        => $expense->user_id,
            'category_id'    => $expense->category_id,
            'category_name'  => $expense->category?->name,
            'category_color' => $expense->category?->color,
            'category_icon'  => $expense->category?->icon,
            'display_order'  => $expense->category?->display_order,
            'name'           => $expense->name,
            'quantity'       => $expense->quantity,
            'unit_price'     => (float) $expense->unit_price,
            'frequency'      => $expense->frequency,
            'total_amount'   => $expense->total_amount,
            'paid'           => $expense->paid,
            'payment_date'   => $expense->payment_date?->toDateString(),
            'notes'          => $expense->notes,
            'created_at'     => $expense->created_at?->toISOString(),
            'updated_at'     => $expense->updated_at?->toISOString(),
        ];
    }
}
