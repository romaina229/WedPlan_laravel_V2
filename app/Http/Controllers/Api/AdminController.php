<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminLog;
use App\Models\Expense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    // ── Liste tous les utilisateurs avec leurs stats ───────────────────
    public function users(Request $request): JsonResponse
    {
        $users = User::withCount([
                'expenses',
            ])
            ->latest()
            ->get()
            ->map(function (User $user) {
                $stats = Expense::statsForUser($user->id);
                return [
                    'id'           => $user->id,
                    'username'     => $user->username,
                    'email'        => $user->email,
                    'full_name'    => $user->full_name,
                    'display_name' => $user->display_name,
                    'role'         => $user->role,
                    'is_admin'     => $user->is_admin,
                    'last_login'   => $user->last_login?->toISOString(),
                    'created_at'   => $user->created_at?->toISOString(),
                    'stats'        => [
                        'total_expenses' => $stats['total_items'],
                        'paid_expenses'  => $stats['paid_items'],
                        'grand_total'    => $stats['grand_total'],
                        'paid_total'     => $stats['paid_total'],
                    ],
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $users,
            'count'   => $users->count(),
        ]);
    }

    // ── Journaux d'activité ───────────────────────────────────────────
    public function logs(Request $request): JsonResponse
    {
        $logs = AdminLog::with('user:id,username,full_name')
            ->latest()
            ->take(200)
            ->get()
            ->map(fn($log) => [
                'id'         => $log->id,
                'action'     => $log->action,
                'type'       => $log->action_type,
                'details'    => $log->details,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toISOString(),
                'user'       => $log->user ? [
                    'id'       => $log->user->id,
                    'username' => $log->user->username,
                ] : null,
                'user_id'    => $log->user_id,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }

    // ── Statistiques globales du système ──────────────────────────────
    public function stats(Request $request): JsonResponse
    {
        $totalUsers    = User::count();
        $totalExpenses = Expense::count();
        $totalAmount   = Expense::selectRaw(
            'COALESCE(SUM(quantity * unit_price * frequency), 0) as total'
        )->value('total');
        $paidAmount    = Expense::where('paid', true)
            ->selectRaw('COALESCE(SUM(quantity * unit_price * frequency), 0) as total')
            ->value('total');

        return response()->json([
            'success' => true,
            'data'    => [
                'total_users'    => $totalUsers,
                'total_expenses' => $totalExpenses,
                'total_amount'   => (float) $totalAmount,
                'paid_amount'    => (float) $paidAmount,
            ],
        ]);
    }

    // ── Changer le rôle d'un utilisateur ─────────────────────────────
    public function updateRole(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'role' => ['required', Rule::in(['admin', 'user'])],
        ]);

        // Protéger le compte de l'admin connecté
        if ($id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas modifier votre propre rôle.',
            ], 403);
        }

        $user = User::findOrFail($id);
        $user->update(['role' => $request->role]);

        AdminLog::log(
            $request->user()->id,
            "Changement de rôle: {$user->username} → {$request->role}",
            'admin'
        );

        return response()->json([
            'success' => true,
            'message' => "Rôle de {$user->username} mis à jour.",
            'data'    => ['id' => $user->id, 'role' => $user->role],
        ]);
    }

    // ── Activer / Désactiver un utilisateur ───────────────────────────
    public function toggleUser(Request $request, int $id): JsonResponse
    {
        if ($id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Action impossible sur votre propre compte.',
            ], 403);
        }

        $user = User::findOrFail($id);

        // On utilise le champ email_verified_at comme proxy d'activation
        // ou on peut ajouter un champ `is_active` si besoin
        AdminLog::log(
            $request->user()->id,
            "Toggle utilisateur: {$user->username}",
            'admin'
        );

        return response()->json([
            'success' => true,
            'message' => "Utilisateur {$user->username} modifié.",
        ]);
    }

    // ── Créer un utilisateur ──────────────────────────────────────────
    public function createUser(Request $request): JsonResponse
    {
        $request->validate([
            'username'  => 'required|string|min:3|max:50|unique:users',
            'email'     => 'required|email|max:100|unique:users',
            'password'  => 'required|string|min:6',
            'full_name' => 'nullable|string|max:100',
            'role'      => ['nullable', Rule::in(['admin', 'user'])],
        ]);

        $user = User::create([
            'username'  => $request->username,
            'email'     => $request->email,
            'password'  => $request->password,
            'full_name' => $request->full_name,
            'role'      => $request->role ?? 'user',
        ]);

        AdminLog::log(
            $request->user()->id,
            "Création utilisateur: {$user->username}",
            'admin'
        );

        return response()->json([
            'success' => true,
            'message' => "Utilisateur {$user->username} créé avec succès.",
            'data'    => [
                'id'       => $user->id,
                'username' => $user->username,
                'email'    => $user->email,
                'role'     => $user->role,
            ],
        ], 201);
    }

    // ── Modifier un utilisateur ───────────────────────────────────────
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $request->validate([
            'email'     => "nullable|email|max:100|unique:users,email,{$id}",
            'full_name' => 'nullable|string|max:100',
            'role'      => ['nullable', Rule::in(['admin', 'user'])],
            'password'  => 'nullable|string|min:6',
            'is_active' => 'nullable|boolean',
        ]);

        // Empêcher l'admin de retirer son propre rôle admin
        if ($id === $request->user()->id && $request->role === 'user') {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas retirer votre propre rôle admin.',
            ], 403);
        }

        $payload = array_filter([
            'email'     => $request->email,
            'full_name' => $request->full_name,
            'role'      => $request->role,
        ], fn($v) => !is_null($v));

        if ($request->filled('password')) {
            $payload['password'] = Hash::make($request->password);
        }

        $user->update($payload);

        AdminLog::log(
            $request->user()->id,
            "Modification utilisateur: {$user->username}",
            'admin'
        );

        return response()->json([
            'success' => true,
            'message' => "Utilisateur {$user->username} mis à jour.",
            'data'    => [
                'id'       => $user->id,
                'username' => $user->username,
                'email'    => $user->email,
                'role'     => $user->role,
            ],
        ]);
    }

    // ── Supprimer un utilisateur ──────────────────────────────────────
    public function deleteUser(Request $request, int $id): JsonResponse
    {
        if ($id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 403);
        }

        $user = User::findOrFail($id);
        $username = $user->username;

        // Révoquer les tokens Sanctum avant suppression
        $user->tokens()->delete();
        $user->delete();

        AdminLog::log(
            $request->user()->id,
            "Suppression utilisateur: {$username}",
            'admin'
        );

        return response()->json([
            'success' => true,
            'message' => "Utilisateur {$username} supprimé définitivement.",
        ]);
    }
}