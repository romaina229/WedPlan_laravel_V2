<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WeddingDate;
use App\Models\WeddingSponsor;
use App\Models\SponsorComment;
use App\Models\Category;
use App\Models\Expense;
use App\Models\AdminLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// ================================================================
// WeddingController
// ================================================================
class WeddingController extends Controller
{
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

    public function saveInfo(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $validator = Validator::make($request->all(), [
            'wedding_date'        => 'required|date|after_or_equal:today',
            'fiance_nom_complet'  => 'nullable|string|max:200',
            'fiancee_nom_complet' => 'nullable|string|max:200',
            'budget_total'        => 'nullable|numeric|min:0',
        ], [
            'wedding_date.after_or_equal' => 'La date doit être aujourd\'hui ou dans le futur.',
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

    public function fullStats(Request $request): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->first();

        if (!$wedding) {
            return response()->json(['success' => false, 'message' => 'Aucune date de mariage définie.'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge(
                $this->formatWedding($wedding),
                ['wedding_stats' => $wedding->wedding_stats]
            ),
        ]);
    }

    private function formatWedding(WeddingDate $w): array
    {
        return [
            'id'                  => $w->id,
            'user_id'             => $w->user_id,
            'fiance_nom_complet'  => $w->fiance_nom_complet,
            'fiancee_nom_complet' => $w->fiancee_nom_complet,
            'budget_total'        => (float) $w->budget_total,
            'wedding_date'        => $w->wedding_date?->toDateString(),
            'days_until'          => $w->days_until_wedding,
            'countdown'           => $w->countdown,
            'created_at'          => $w->created_at?->toISOString(),
            'updated_at'          => $w->updated_at?->toISOString(),
        ];
    }
}


// ================================================================
// CategoryController
// ================================================================
class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::ordered()->get();

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255|unique:categories',
            'color' => 'nullable|string|max:7',
            'icon'  => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        if (Category::count() >= 50) {
            return response()->json(['success' => false, 'message' => 'Limite de 50 catégories atteinte.'], 429);
        }

        $category = Category::create([
            'name'          => trim($request->name),
            'color'         => $request->color ?? '#8b4f8d',
            'icon'          => $request->icon  ?? 'fas fa-folder',
            'display_order' => Category::max('display_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Catégorie ajoutée.',
            'data'    => $category,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'  => "required|string|max:255|unique:categories,name,{$id}",
            'color' => 'nullable|string|max:7',
            'icon'  => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $category->update($request->only(['name', 'color', 'icon']));

        return response()->json([
            'success' => true,
            'message' => 'Catégorie mise à jour.',
            'data'    => $category->fresh(),
        ]);
    }
}


// ================================================================
// SponsorController
// ================================================================
class SponsorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->first();

        if (!$wedding) {
            return response()->json(['success' => false, 'message' => 'Aucun mariage configuré.'], 404);
        }

        $sponsors = WeddingSponsor::where('wedding_dates_id', $wedding->id)
            ->withCount('comments')
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $sponsors]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId  = $request->user()->id;
        $wedding = WeddingDate::where('user_id', $userId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'sponsor_nom_complet'          => 'required|string|max:200',
            'sponsor_conjoint_nom_complet' => 'required|string|max:200',
            'email'                        => 'required|email|max:150|unique:wedding_sponsors',
            'password'                     => 'required|string|min:6',
            'telephone'                    => 'nullable|string|max:20',
            'role'                         => 'in:parrain,conseiller',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $sponsor = WeddingSponsor::create([
            'wedding_dates_id'             => $wedding->id,
            'sponsor_nom_complet'          => $request->sponsor_nom_complet,
            'sponsor_conjoint_nom_complet' => $request->sponsor_conjoint_nom_complet,
            'email'                        => $request->email,
            'password_hash'                => password_hash($request->password, PASSWORD_DEFAULT),
            'telephone'                    => $request->telephone,
            'role'                         => $request->role ?? 'parrain',
            'statut'                       => 'actif',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Parrain ajouté avec succès.',
            'data'    => $sponsor,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $sponsor = WeddingSponsor::with('weddingDate')
            ->where('email', $request->email)
            ->where('statut', 'actif')
            ->first();

        if (!$sponsor || !$sponsor->verifyPassword($request->password)) {
            return response()->json(['success' => false, 'message' => 'Identifiants incorrects.'], 401);
        }

        $sponsor->logActivity('connexion');

        return response()->json([
            'success'  => true,
            'message'  => 'Connexion réussie.',
            'sponsor'  => $sponsor->makeVisible([]),
            'wedding'  => $sponsor->weddingDate,
        ]);
    }

    public function comments(Request $request, int $sponsorId): JsonResponse
    {
        $comments = SponsorComment::with('sponsor', 'expense')
            ->where('sponsor_id', $sponsorId)
            ->public()
            ->latest()
            ->get();

        return response()->json(['success' => true, 'data' => $comments]);
    }

    public function addComment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sponsor_id'      => 'required|exists:wedding_sponsors,id',
            'commentaire'     => 'required|string|max:2000',
            'type_commentaire'=> 'in:general,depense,suggestion',
            'expense_id'      => 'nullable|exists:expenses,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $sponsor = WeddingSponsor::findOrFail($request->sponsor_id);

        $comment = SponsorComment::create([
            'wedding_dates_id'  => $sponsor->wedding_dates_id,
            'sponsor_id'        => $sponsor->id,
            'expense_id'        => $request->expense_id,
            'commentaire'       => $request->commentaire,
            'type_commentaire'  => $request->type_commentaire ?? 'general',
            'statut'            => 'public',
        ]);

        $sponsor->logActivity('commentaire', "Commentaire #{$comment->id}");

        // Notify user
        $wedding = $sponsor->weddingDate;
        \App\Models\Notification::create([
            'user_id'           => $wedding->user_id,
            'wedding_dates_id'  => $wedding->id,
            'type_notification' => 'nouveau_commentaire_parrain',
            'message'           => "Nouveau commentaire de {$sponsor->sponsor_nom_complet}: " . mb_substr($comment->commentaire, 0, 100),
            'is_read'           => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Commentaire ajouté.',
            'data'    => $comment->load('sponsor'),
        ], 201);
    }
}


// ================================================================
// NotificationController
// ================================================================
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = \App\Models\Notification::where('user_id', $request->user()->id)
            ->latest()
            ->take(50)
            ->get();

        return response()->json(['success' => true, 'data' => $notifications]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notif = \App\Models\Notification::where('user_id', $request->user()->id)->findOrFail($id);
        $notif->markAsRead();

        return response()->json(['success' => true, 'message' => 'Notification lue.']);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        \App\Models\Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true, 'message' => 'Toutes les notifications sont lues.']);
    }
}


// ================================================================
// ExportController
// ================================================================
class ExportController extends Controller
{
    public function exportCsv(Request $request): \Illuminate\Http\Response
    {
        $userId = $request->user()->id;
        $filter = $request->get('filter', 'all');

        $query = Expense::with('category')->forUser($userId);

        if ($filter === 'paid')   $query->paid();
        if ($filter === 'unpaid') $query->unpaid();

        $expenses = $query->orderBy('category_id')->get();

        $rows[] = ['Catégorie', 'Dépense', 'Quantité', 'Prix unitaire', 'Fréquence', 'Total', 'Statut', 'Date paiement', 'Notes'];

        foreach ($expenses as $e) {
            $rows[] = [
                $e->category?->name,
                $e->name,
                $e->quantity,
                $e->unit_price,
                $e->frequency,
                $e->total_amount,
                $e->paid ? 'Payé' : 'Non payé',
                $e->payment_date?->toDateString() ?? '',
                $e->notes ?? '',
            ];
        }

        $csv    = '';
        foreach ($rows as $row) {
            $csv .= implode(';', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row)) . "\r\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="budget_mariage_' . now()->format('Ymd') . '.csv"',
        ])->setContent("\xEF\xBB\xBF" . $csv);  // BOM for Excel
    }
}
