<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\AdminLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    // ── Liste toutes les catégories ───────────────────────────────────
    public function index(): JsonResponse
    {
        $categories = Category::ordered()->get();

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ]);
    }

    // ── Créer une catégorie ───────────────────────────────────────────
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255|unique:categories,name',
            'color' => 'nullable|string|max:7',
            'icon'  => 'nullable|string|max:50',
        ], [
            'name.unique' => 'Une catégorie avec ce nom existe déjà.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (Category::count() >= 50) {
            return response()->json([
                'success' => false,
                'message' => 'Limite de 50 catégories atteinte.',
            ], 429);
        }

        $category = Category::create([
            'name'          => trim($request->name),
            'color'         => $request->color ?? '#8b4f8d',
            'icon'          => $request->icon  ?? 'fas fa-folder',
            'display_order' => (Category::max('display_order') ?? 0) + 1,
        ]);

        AdminLog::log($request->user()?->id, "Catégorie créée: {$category->name}", 'data');

        return response()->json([
            'success' => true,
            'message' => 'Catégorie créée avec succès.',
            'data'    => $category,
        ], 201);
    }

    // ── Modifier une catégorie ────────────────────────────────────────
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'  => "required|string|max:255|unique:categories,name,{$id}",
            'color' => 'nullable|string|max:7',
            'icon'  => 'nullable|string|max:50',
        ], [
            'name.unique' => 'Une autre catégorie porte déjà ce nom.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $category->update([
            'name'  => trim($request->name),
            'color' => $request->color ?? $category->color,
            'icon'  => $request->icon  ?? $category->icon,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Catégorie mise à jour.',
            'data'    => $category->fresh(),
        ]);
    }

    // ── Supprimer une catégorie ───────────────────────────────────────
    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        // Vérifier si des dépenses l'utilisent
        if ($category->expenses()->exists()) {
            return response()->json([
                'success' => false,
                'message' => "Impossible de supprimer : des dépenses utilisent cette catégorie. Supprimez ou réaffectez-les d'abord.",
            ], 409);
        }

        $name = $category->name;
        $category->delete();

        AdminLog::log($request->user()?->id, "Catégorie supprimée: {$name}", 'data');

        return response()->json([
            'success' => true,
            'message' => "Catégorie « {$name} » supprimée.",
        ]);
    }
}
