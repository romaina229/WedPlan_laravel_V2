<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WeddingDate;
use App\Models\AdminLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WeddingController extends Controller
{
    // ── Afficher les informations du mariage ──────────────────────────
    public function show(Request $request): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->first();

        if (!$wedding) {
            return response()->json(['success' => true, 'data' => null]);
        }

        return response()->json([
            'success' => true,
            'data'    => $this->formatWedding($wedding),
        ]);
    }

    // ── Sauvegarder / mettre à jour les informations du mariage ───────
    public function saveInfo(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validator = Validator::make($request->all(), [
            // On accepte les dates passées pour les tests et la démo
            'wedding_date'        => 'required|date',
            'fiance_nom_complet'  => 'nullable|string|max:200',
            'fiancee_nom_complet' => 'nullable|string|max:200',
            'budget_total'        => 'nullable|numeric|min:0|max:999999999',
        ], [
            'wedding_date.required' => 'La date du mariage est obligatoire.',
            'wedding_date.date'     => 'Format de date invalide.',
            'budget_total.min'      => 'Le budget ne peut pas être négatif.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $wedding = WeddingDate::updateOrCreate(
            ['user_id' => $userId],
            [
                'wedding_date'        => $request->wedding_date,
                'fiance_nom_complet'  => $request->fiance_nom_complet,
                'fiancee_nom_complet' => $request->fiancee_nom_complet,
                'budget_total'        => $request->budget_total ?? 0,
            ]
        );

        AdminLog::log($userId, "Mise à jour date mariage", 'data');

        return response()->json([
            'success' => true,
            'message' => 'Informations du mariage sauvegardées.',
            'data'    => $this->formatWedding($wedding),
        ]);
    }

    // ── Statistiques complètes du mariage ─────────────────────────────
    public function fullStats(Request $request): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->first();

        if (!$wedding) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune date de mariage définie.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge(
                $this->formatWedding($wedding),
                ['wedding_stats' => $wedding->wedding_stats]
            ),
        ]);
    }

    // ── Formateur ─────────────────────────────────────────────────────
    private function formatWedding(WeddingDate $w): array
    {
        $weddingDate = $w->wedding_date;
        $now         = now()->startOfDay();
        $daysUntil   = $now->diffInDays($weddingDate, false);
        $isPast      = $weddingDate < $now;

        return [
            'id'                  => $w->id,
            'user_id'             => $w->user_id,
            'fiance_nom_complet'  => $w->fiance_nom_complet,
            'fiancee_nom_complet' => $w->fiancee_nom_complet,
            'budget_total'        => (float) $w->budget_total,
            'wedding_date'        => $weddingDate?->toDateString(),
            'days_until'          => (int) max(0, $daysUntil),
            'days_since'          => $isPast ? (int) abs($daysUntil) : 0,
            'is_past'             => $isPast,
            'countdown'           => $w->countdown,
            'created_at'          => $w->created_at?->toISOString(),
            'updated_at'          => $w->updated_at?->toISOString(),
        ];
    }
}
