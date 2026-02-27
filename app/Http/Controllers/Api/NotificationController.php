<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // ── Liste des notifications de l'utilisateur ──────────────────────
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->latest()
            ->take(50)
            ->get()
            ->map(fn($n) => $this->formatNotif($n));

        return response()->json([
            'success' => true,
            'data'    => $notifications,
        ]);
    }

    // ── Marquer une notification comme lue ────────────────────────────
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notif = Notification::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $notif->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue.',
        ]);
    }

    // ── Tout marquer comme lu ─────────────────────────────────────────
    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications sont marquées comme lues.',
        ]);
    }

    // ── Formateur ─────────────────────────────────────────────────────
    private function formatNotif(Notification $n): array
    {
        // Déduire le titre depuis le type_notification
        $titles = [
            'nouveau_commentaire_parrain' => 'Nouveau commentaire',
            'rappel_paiement'             => 'Rappel de paiement',
            'budget_depassement'          => 'Dépassement de budget',
            'mariage_proche'              => 'Le mariage approche !',
        ];

        // Déduire le type (couleur/icône) depuis le type_notification
        $types = [
            'nouveau_commentaire_parrain' => 'info',
            'rappel_paiement'             => 'warning',
            'budget_depassement'          => 'danger',
            'mariage_proche'              => 'success',
        ];

        return [
            'id'         => $n->id,
            'title'      => $titles[$n->type_notification] ?? 'Notification',
            'message'    => $n->message,
            'type'       => $types[$n->type_notification]  ?? 'info',
            'is_read'    => (bool) $n->is_read,
            'created_at' => $n->created_at?->toISOString(),
        ];
    }
}
