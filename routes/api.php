<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\WeddingController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\SponsorController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\AdminController;

/*
|--------------------------------------------------------------------------
| WedPlan API Routes — Laravel Sanctum
|--------------------------------------------------------------------------
*/

// ── Routes publiques ──────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
});

// Connexion publique parrain + tableau de bord parrain (sans auth Sanctum)
Route::post('sponsors/login',                    [SponsorController::class, 'login']);
Route::post('sponsors/comments',                 [SponsorController::class, 'addComment']);
Route::get('sponsors/dashboard/{sponsorId}',     [SponsorController::class, 'dashboard']);
Route::get('sponsors/dashboard/{sponsorId}/comments', [SponsorController::class, 'dashboardComments']);
// ── Reset password (public) ───────────────────────────────────────────────
Route::post('auth/forgot-password',     [AuthController::class, 'forgotPassword']);
Route::post('auth/verify-reset-code',   [AuthController::class, 'verifyResetCode']);
Route::post('auth/reset-password',      [AuthController::class, 'resetPassword']);
Route::post('sponsors/forgot-password', [AuthController::class, 'forgotSponsorPassword']);
Route::post('sponsors/reset-password',  [AuthController::class, 'resetSponsorPassword']);

// ── Routes protégées (Sanctum) ────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ── Authentification & Profil ──────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('logout',           [AuthController::class, 'logout']);
        Route::get('me',                [AuthController::class, 'me']);
        Route::put('profile',           [AuthController::class, 'updateProfile']);
        Route::put('change-password',   [AuthController::class, 'changePassword']);
    });

    // ── Mariage ────────────────────────────────────────────────────────
    Route::prefix('wedding')->group(function () {
        Route::get('/',     [WeddingController::class, 'show']);
        Route::post('/',    [WeddingController::class, 'saveInfo']);
        Route::get('stats', [WeddingController::class, 'fullStats']);
    });

    // ── Dépenses ───────────────────────────────────────────────────────
    Route::prefix('expenses')->group(function () {
        Route::get('stats',          [ExpenseController::class, 'stats']);
        Route::get('category-stats', [ExpenseController::class, 'categoryStats']);
        Route::post('bulk-paid',     [ExpenseController::class, 'bulkMarkPaid']);
        Route::get('/',              [ExpenseController::class, 'index']);
        Route::post('/',             [ExpenseController::class, 'store']);
        Route::get('{id}',           [ExpenseController::class, 'show']);
        Route::put('{id}',           [ExpenseController::class, 'update']);
        Route::delete('{id}',        [ExpenseController::class, 'destroy']);
        Route::patch('{id}/toggle-paid', [ExpenseController::class, 'togglePaid']);
    });

    // ── Catégories ─────────────────────────────────────────────────────
    Route::prefix('categories')->group(function () {
        Route::get('/',    [CategoryController::class, 'index']);
        Route::post('/',   [CategoryController::class, 'store']);
        Route::put('{id}', [CategoryController::class, 'update']);
        Route::delete('{id}', [CategoryController::class, 'destroy']);
    });

    // ── Parrains ───────────────────────────────────────────────────────
    Route::prefix('sponsors')->group(function () {
        Route::get('/',              [SponsorController::class, 'index']);
        Route::post('/',             [SponsorController::class, 'store']);
        Route::get('{id}',           [SponsorController::class, 'show']);
        Route::put('{id}',           [SponsorController::class, 'update']);
        Route::delete('{id}',        [SponsorController::class, 'destroy']);
        Route::get('{id}/comments',  [SponsorController::class, 'comments']);
        Route::patch('{id}/toggle-status', [SponsorController::class, 'toggleStatus']);
    });

    // ── Notifications ──────────────────────────────────────────────────
    Route::prefix('notifications')->group(function () {
        Route::get('/',              [NotificationController::class, 'index']);
        Route::patch('{id}/read',    [NotificationController::class, 'markRead']);
        Route::post('mark-all-read', [NotificationController::class, 'markAllRead']);
    });

    // ── Export ─────────────────────────────────────────────────────────
    Route::get('export/csv', [ExportController::class, 'exportCsv']);

    // ── Administration (Admin uniquement) ──────────────────────────────
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('users',     [AdminController::class, 'users']);
        Route::get('logs',      [AdminController::class, 'logs']);
        Route::get('stats',     [AdminController::class, 'stats']);
        Route::patch('users/{id}/role',   [AdminController::class, 'updateRole']);
        Route::patch('users/{id}/toggle', [AdminController::class, 'toggleUser']);
        Route::post('users',              [AdminController::class, 'createUser']);
        Route::put('users/{id}',          [AdminController::class, 'updateUser']);
        Route::delete('users/{id}',       [AdminController::class, 'deleteUser']);
    });
});