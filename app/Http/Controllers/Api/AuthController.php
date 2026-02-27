<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AdminLog;
use App\Models\WeddingSponsor;
use App\Mail\ResetPasswordMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // ─── Register ─────────────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username'  => 'required|string|min:3|max:50|unique:users',
            'email'     => 'required|email|max:100|unique:users',
            'password'  => ['required', 'confirmed', Password::min(6)],
            'full_name' => 'nullable|string|max:100',
        ], [
            'username.min'    => "Le nom d'utilisateur doit contenir au moins 3 caractères.",
            'username.unique' => "Ce nom d'utilisateur est déjà pris.",
            'email.unique'    => "Cet e-mail est déjà utilisé.",
            'password.min'    => "Le mot de passe doit contenir au moins 6 caractères.",
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'username'  => $request->username,
            'email'     => $request->email,
            'password'  => $request->password,
            'full_name' => $request->full_name,
            'role'      => 'user',
        ]);

        AdminLog::log(null, "Inscription: {$user->username}", 'auth', "Email: {$user->email}");

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inscription réussie ! Bienvenue sur WedPlan.',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ], 201);
    }

    // ─── Login ────────────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = User::where('username', $request->login)
            ->orWhere('email', $request->login)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            AdminLog::log(null, "Échec connexion: {$request->login}", 'auth', 'Identifiants incorrects');
            return response()->json([
                'success' => false,
                'message' => "Nom d'utilisateur ou mot de passe incorrect.",
            ], 401);
        }

        $user->tokens()->delete();
        $user->updateLastLogin();
        $token = $user->createToken('auth_token')->plainTextToken;

        AdminLog::log($user->id, "Connexion réussie", 'auth');

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie. Bon planning !',
            'token'   => $token,
            'user'    => $this->formatUser($user),
        ]);
    }

    // ─── Logout ───────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        AdminLog::log($user?->id, "Déconnexion", 'auth');
        $request->user()?->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie.',
        ]);
    }

    // ─── Me ───────────────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('weddingDate');

        return response()->json([
            'success' => true,
            'user'    => array_merge($this->formatUser($user), [
                'wedding_date'  => $user->weddingDate,
                'unread_notifs' => $user->getUnreadNotificationsCount(),
            ]),
        ]);
    }

    // ─── Update Profile ───────────────────────────────────────────

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'email'     => "required|email|max:100|unique:users,email,{$user->id}",
            'full_name' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user->update([
            'email'     => $request->email,
            'full_name' => $request->full_name,
        ]);

        AdminLog::log($user->id, "Mise à jour profil", 'auth');

        return response()->json([
            'success' => true,
            'message' => 'Profil mis à jour avec succès.',
            'user'    => $this->formatUser($user->fresh()),
        ]);
    }

    // ─── Change Password ──────────────────────────────────────────

    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password'         => ['required', 'confirmed', Password::min(6)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Mot de passe actuel incorrect.',
            ], 422);
        }

        $user->update(['password' => $request->password]);
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        AdminLog::log($user->id, "Changement de mot de passe", 'auth');

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès.',
            'token'   => $token,
        ]);
    }

    // ─── Mot de passe oublié — Demande code ───────────────────────

    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Email invalide.'], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Si cet email existe, un code vous a été envoyé.',
            ]);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_resets')->where('email', $request->email)->delete();
        DB::table('password_resets')->insert([
            'email'      => $request->email,
            'token'      => $code,
            'expires_at' => now()->addMinutes(15),
            'used'       => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            Mail::to($request->email)->send(new ResetPasswordMail($user->full_name ?? $user->username, $code));
        } catch (\Exception $e) {
            Log::warning('Mail reset non envoyé: ' . $e->getMessage());
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Code envoyé à votre adresse email.',
            'debug_code' => app()->isLocal() ? $code : null,
        ]);
    }

    // ─── Mot de passe oublié — Vérifier code ─────────────────────

    public function verifyResetCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Données invalides.'], 422);
        }

        $reset = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$reset) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide ou expiré.',
            ], 400);
        }

        return response()->json(['success' => true, 'message' => 'Code valide.']);
    }

    // ─── Mot de passe oublié — Nouveau mot de passe ───────────────

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'code'     => 'required|string|size:6',
            'password' => ['required', 'confirmed', 'min:8'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $reset = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$reset) {
            return response()->json([
                'success' => false,
                'message' => 'Code invalide ou expiré.',
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        $user->update(['password' => Hash::make($request->password)]);
        DB::table('password_resets')->where('email', $request->email)->update(['used' => true]);
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès.',
        ]);
    }

    // ─── Reset parrain — Demande code ─────────────────────────────

    public function forgotSponsorPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), ['email' => 'required|email']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Email invalide.'], 422);
        }

        $sponsor = WeddingSponsor::where('email', $request->email)->first();

        if (!$sponsor) {
            return response()->json([
                'success' => true,
                'message' => 'Si cet email existe, un code vous a été envoyé.',
            ]);
        }

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_resets')->where('email', $request->email)->delete();
        DB::table('password_resets')->insert([
            'email'      => $request->email,
            'token'      => $code,
            'expires_at' => now()->addMinutes(15),
            'used'       => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            Mail::to($request->email)->send(new ResetPasswordMail($sponsor->sponsor_nom_complet, $code));
        } catch (\Exception $e) {
            Log::warning('Mail sponsor reset non envoyé: ' . $e->getMessage());
        }

        return response()->json([
            'success'    => true,
            'message'    => 'Code envoyé à votre adresse email.',
            'debug_code' => app()->isLocal() ? $code : null,
        ]);
    }

    // ─── Reset parrain — Nouveau mot de passe ─────────────────────

    public function resetSponsorPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'code'     => 'required|string|size:6',
            'password' => 'required|confirmed|min:8',
        ]);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $reset = DB::table('password_resets')
            ->where('email', $request->email)
            ->where('token', $request->code)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$reset) {
            return response()->json(['success' => false, 'message' => 'Code invalide ou expiré.'], 400);
        }

        $sponsor = WeddingSponsor::where('email', $request->email)->first();
        if (!$sponsor) {
            return response()->json(['success' => false, 'message' => 'Sponsor introuvable.'], 404);
        }

        $sponsor->update(['password_hash' => Hash::make($request->password)]);
        DB::table('password_resets')->where('email', $request->email)->update(['used' => true]);

        return response()->json(['success' => true, 'message' => 'Mot de passe réinitialisé avec succès.']);
    }

    // ─── Helpers ──────────────────────────────────────────────────

    private function formatUser(User $user): array
    {
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
        ];
    }
}
