<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WeddingDate;
use App\Models\WeddingSponsor;
use App\Models\SponsorComment;
use App\Models\Expense;
use App\Models\AdminLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SponsorController extends Controller
{
    // ── Liste des parrains de l'utilisateur connecté ──────────────────
    public function index(Request $request): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->first();

        // Pas d'erreur bloquante si pas de mariage configuré
        if (!$wedding) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $sponsors = WeddingSponsor::where('wedding_dates_id', $wedding->id)
            ->withCount('comments')
            ->latest()
            ->get()
            ->map(fn($s) => $this->formatSponsor($s));

        return response()->json([
            'success' => true,
            'data'    => $sponsors,
        ]);
    }

    // ── Détail d'un parrain ───────────────────────────────────────────
    public function show(Request $request, int $id): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->firstOrFail();

        $sponsor = WeddingSponsor::where('wedding_dates_id', $wedding->id)
            ->withCount('comments')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatSponsor($sponsor),
        ]);
    }

    // ── Créer un parrain ──────────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->first();

        if (!$wedding) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez d\'abord configurer votre mariage avant d\'ajouter des parrains.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'sponsor_nom_complet'          => 'required|string|max:200',
            'sponsor_conjoint_nom_complet' => 'required|string|max:200',
            'email'                        => 'required|email|max:150|unique:wedding_sponsors,email',
            'password'                     => 'required|string|min:6',
            'telephone'                    => 'nullable|string|max:20',
            'role'                         => 'nullable|in:parrain,conseiller',
        ], [
            'email.unique'    => 'Cet email est déjà utilisé par un parrain.',
            'password.min'    => 'Le mot de passe doit contenir au moins 6 caractères.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $sponsor = WeddingSponsor::create([
            'wedding_dates_id'             => $wedding->id,
            'sponsor_nom_complet'          => trim($request->sponsor_nom_complet),
            'sponsor_conjoint_nom_complet' => trim($request->sponsor_conjoint_nom_complet),
            'email'                        => strtolower($request->email),
            'password_hash'                => password_hash($request->password, PASSWORD_DEFAULT),
            'telephone'                    => $request->telephone,
            'role'                         => $request->role ?? 'parrain',
            'statut'                       => 'actif',
        ]);

        AdminLog::log($userId, "Parrain ajouté: {$sponsor->sponsor_nom_complet}", 'data');

        return response()->json([
            'success' => true,
            'message' => 'Parrain ajouté avec succès.',
            'data'    => $this->formatSponsor($sponsor->loadCount('comments')),
        ], 201);
    }

    // ── Modifier un parrain ───────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->firstOrFail();
        $sponsor = WeddingSponsor::where('wedding_dates_id', $wedding->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'sponsor_nom_complet'          => 'required|string|max:200',
            'sponsor_conjoint_nom_complet' => 'required|string|max:200',
            'email'                        => "required|email|max:150|unique:wedding_sponsors,email,{$id}",
            'password'                     => 'nullable|string|min:6',
            'telephone'                    => 'nullable|string|max:20',
            'role'                         => 'nullable|in:parrain,conseiller',
            'statut'                       => 'nullable|in:actif,inactif,en_attente',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $updateData = [
            'sponsor_nom_complet'          => trim($request->sponsor_nom_complet),
            'sponsor_conjoint_nom_complet' => trim($request->sponsor_conjoint_nom_complet),
            'email'                        => strtolower($request->email),
            'telephone'                    => $request->telephone,
            'role'                         => $request->role   ?? $sponsor->role,
            'statut'                       => $request->statut ?? $sponsor->statut,
        ];

        if ($request->filled('password')) {
            $updateData['password_hash'] = password_hash($request->password, PASSWORD_DEFAULT);
        }

        $sponsor->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Parrain mis à jour.',
            'data'    => $this->formatSponsor($sponsor->fresh()->loadCount('comments')),
        ]);
    }

    // ── Supprimer un parrain ──────────────────────────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->firstOrFail();
        $sponsor = WeddingSponsor::where('wedding_dates_id', $wedding->id)->findOrFail($id);

        $name = $sponsor->sponsor_nom_complet;
        $sponsor->delete();

        AdminLog::log($userId, "Parrain supprimé: {$name}", 'data');

        return response()->json([
            'success' => true,
            'message' => 'Parrain supprimé.',
        ]);
    }

    // ── Changer le statut actif/inactif ───────────────────────────────
    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->firstOrFail();
        $sponsor = WeddingSponsor::where('wedding_dates_id', $wedding->id)->findOrFail($id);

        $sponsor->statut = $sponsor->statut === 'actif' ? 'inactif' : 'actif';
        $sponsor->save();

        return response()->json([
            'success' => true,
            'message' => "Statut mis à jour : {$sponsor->statut}",
            'data'    => ['id' => $sponsor->id, 'statut' => $sponsor->statut],
        ]);
    }

    // ── Tableau de bord parrain (public — après login parrain) ────────
    public function dashboard(Request $request, int $sponsorId): JsonResponse
    {
        $sponsor = WeddingSponsor::with('weddingDate')->findOrFail($sponsorId);

        if ($sponsor->statut !== 'actif') {
            return response()->json(['success' => false, 'message' => 'Compte inactif.'], 403);
        }

        $wedding = $sponsor->weddingDate;
        if (!$wedding) {
            return response()->json(['success' => false, 'message' => 'Mariage introuvable.'], 404);
        }

        $userId = $wedding->user_id;

        // ── Statistiques des dépenses ──────────────────────────────────
        $expenses = \App\Models\Expense::with('category')
            ->where('user_id', $userId)
            ->get();

        $totalDepense = $expenses->sum(fn($e) => $e->quantity * $e->unit_price * $e->frequency);
        $totalPaye    = $expenses->where('paid', true)->sum(fn($e) => $e->quantity * $e->unit_price * $e->frequency);
        $totalNonPaye = $totalDepense - $totalPaye;
        $nbDepenses   = $expenses->count();
        $nbPayes      = $expenses->where('paid', true)->count();
        $pct          = $totalDepense > 0 ? round(($totalPaye / $totalDepense) * 100, 1) : 0;

        // ── Statistiques par catégorie ─────────────────────────────────
        $byCategory = $expenses->groupBy('category_id')->map(function ($group) {
            $cat   = $group->first()->category;
            $total = $group->sum(fn($e) => $e->quantity * $e->unit_price * $e->frequency);
            $paid  = $group->where('paid', true)->sum(fn($e) => $e->quantity * $e->unit_price * $e->frequency);
            return [
                'category_name'  => $cat?->name ?? 'Autre',
                'category_color' => $cat?->color ?? '#888',
                'category_icon'  => $cat?->icon  ?? '📦',
                'total'          => round($total, 0),
                'paid'           => round($paid, 0),
                'count'          => $group->count(),
                'pct_paid'       => $total > 0 ? round(($paid / $total) * 100, 1) : 0,
            ];
        })->values();

        // ── Liste des dépenses formatées ───────────────────────────────
        $expensesList = $expenses->sortByDesc('created_at')->map(fn($e) => [
            'id'             => $e->id,
            'name'           => $e->name,
            'notes'          => $e->notes,
            'quantity'       => $e->quantity,
            'unit_price'     => $e->unit_price,
            'frequency'      => $e->frequency,
            'montant_total'  => round($e->quantity * $e->unit_price * $e->frequency, 0),
            'paid'           => $e->paid,
            'payment_date'   => $e->payment_date?->toDateString(),
            'created_at'     => $e->created_at?->toISOString(),
            'category_name'  => $e->category?->name  ?? 'Autre',
            'category_color' => $e->category?->color ?? '#888',
            'category_icon'  => $e->category?->icon  ?? '📦',
        ])->values();

        // ── Commentaires du mariage ────────────────────────────────────
        $comments = \App\Models\SponsorComment::with('sponsor:id,sponsor_nom_complet,role')
            ->where('wedding_dates_id', $wedding->id)
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($c) => [
                'id'         => $c->id,
                'content'    => $c->commentaire,
                'type'       => $c->type_commentaire,
                'expense_id' => $c->expense_id,
                'author'     => $c->sponsor?->sponsor_nom_complet ?? 'Parrain',
                'role'       => $c->sponsor?->role ?? 'parrain',
                'is_mine'    => $c->sponsor_id === $sponsorId,
                'created_at' => $c->created_at?->toISOString(),
            ]);

        $sponsor->logActivity('dashboard', 'Consultation du tableau de bord');

        return response()->json([
            'success' => true,
            'data' => [
                'wedding' => [
                    'id'                   => $wedding->id,
                    'fiance_nom_complet'   => $wedding->fiance_nom_complet,
                    'fiancee_nom_complet'  => $wedding->fiancee_nom_complet,
                    'wedding_date'         => $wedding->wedding_date,
                    'budget_total'         => $wedding->budget_total ?? 0,
                    'lieu_ceremonie'       => $wedding->lieu_ceremonie ?? null,
                ],
                'stats' => [
                    'budget_total'    => $wedding->budget_total ?? 0,
                    'total_depense'   => round($totalDepense, 0),
                    'total_paye'      => round($totalPaye, 0),
                    'total_non_paye'  => round($totalNonPaye, 0),
                    'nombre_depenses' => $nbDepenses,
                    'nombre_payes'    => $nbPayes,
                    'pourcentage_paye'=> $pct,
                    'budget_restant'  => round(($wedding->budget_total ?? 0) - $totalDepense, 0),
                ],
                'by_category' => $byCategory,
                'expenses'    => $expensesList,
                'comments'    => $comments,
            ],
        ]);
    }

    // ── Commentaires du tableau de bord parrain ───────────────────────
    public function dashboardComments(Request $request, int $sponsorId): JsonResponse
    {
        $sponsor = WeddingSponsor::findOrFail($sponsorId);
        $wedding = $sponsor->weddingDate;

        if (!$wedding) {
            return response()->json(['success' => false, 'data' => []]);
        }

        $comments = \App\Models\SponsorComment::with('sponsor:id,sponsor_nom_complet,role')
            ->where('wedding_dates_id', $wedding->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn($c) => [
                'id'         => $c->id,
                'content'    => $c->commentaire,
                'type'       => $c->type_commentaire,
                'expense_id' => $c->expense_id,
                'author'     => $c->sponsor?->sponsor_nom_complet ?? 'Parrain',
                'role'       => $c->sponsor?->role ?? 'parrain',
                'is_mine'    => $c->sponsor_id === $sponsorId,
                'created_at' => $c->created_at?->toISOString(),
            ]);

        return response()->json(['success' => true, 'data' => $comments]);
    }

    // ── Connexion d'un parrain (public) ───────────────────────────────
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $sponsor = WeddingSponsor::with('weddingDate')
            ->where('email', strtolower($request->email))
            ->where('statut', 'actif')
            ->first();

        if (!$sponsor || !password_verify($request->password, $sponsor->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Email ou mot de passe incorrect.',
            ], 401);
        }

        $sponsor->logActivity('connexion');

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie.',
            'sponsor' => $this->formatSponsor($sponsor),
            'wedding' => $sponsor->weddingDate,
        ]);
    }

    // ── Commentaires d'un parrain ─────────────────────────────────────
    public function comments(Request $request, int $id): JsonResponse
    {
        $comments = SponsorComment::with('sponsor:id,sponsor_nom_complet')
            ->where('sponsor_id', $id)
            ->where('statut', 'public')
            ->latest()
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'content'          => $c->commentaire,
                'type'             => $c->type_commentaire,
                'author'           => $c->sponsor?->sponsor_nom_complet ?? 'Parrain',
                'expense_id'       => $c->expense_id,
                'created_at'       => $c->created_at?->toISOString(),
            ]);

        return response()->json([
            'success' => true,
            'data'    => $comments,
        ]);
    }

    // ── Ajouter un commentaire (public — parrain non auth) ────────────
    public function addComment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sponsor_id'       => 'required|exists:wedding_sponsors,id',
            'content'          => 'required|string|max:2000',
            'type_commentaire' => 'nullable|in:general,depense,suggestion',
            'expense_id'       => 'nullable|exists:expenses,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $sponsor = WeddingSponsor::findOrFail($request->sponsor_id);

        $comment = SponsorComment::create([
            'wedding_dates_id' => $sponsor->wedding_dates_id,
            'sponsor_id'       => $sponsor->id,
            'expense_id'       => $request->expense_id,
            'commentaire'      => trim($request->content),
            'type_commentaire' => $request->type_commentaire ?? 'general',
            'statut'           => 'public',
        ]);

        $sponsor->logActivity('commentaire', "Commentaire #{$comment->id}");

        // Notifier le propriétaire du mariage
        $wedding = $sponsor->weddingDate;
        if ($wedding) {
            // Récupérer le nom de la dépense liée si présent
            $expenseName = null;
            if ($comment->expense_id) {
                $expense = Expense::find($comment->expense_id);
                $expenseName = $expense?->name;
            }

            \App\Models\Notification::create([
                'user_id'           => $wedding->user_id,
                'wedding_dates_id'  => $wedding->id,
                'type_notification' => 'nouveau_commentaire_parrain',
                'message'           => "Nouveau commentaire de {$sponsor->sponsor_nom_complet} : "
                    . mb_substr($comment->commentaire, 0, 100),
                'is_read'           => false,
                'data'              => json_encode([
                    'expense_id'   => $comment->expense_id,
                    'expense_name' => $expenseName,
                    'sponsor_id'   => $sponsor->id,
                    'comment_id'   => $comment->id,
                ]),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Commentaire ajouté.',
            'data'    => [
                'id'         => $comment->id,
                'content'    => $comment->commentaire,
                'type'       => $comment->type_commentaire,
                'author'     => $sponsor->sponsor_nom_complet,
                'created_at' => $comment->created_at?->toISOString(),
            ],
        ], 201);
    }

    // ── Formateur ─────────────────────────────────────────────────────
    private function formatSponsor(WeddingSponsor $s): array
    {
        return [
            'id'                          => $s->id,
            'wedding_dates_id'            => $s->wedding_dates_id,
            'sponsor_nom_complet'         => $s->sponsor_nom_complet,
            'sponsor_conjoint_nom_complet'=> $s->sponsor_conjoint_nom_complet,
            'email'                       => $s->email,
            'telephone'                   => $s->telephone,
            'role'                        => $s->role,
            'statut'                      => $s->statut,
            'comments_count'              => $s->comments_count ?? 0,
            'created_at'                  => $s->created_at?->toISOString(),
        ];
    }
}