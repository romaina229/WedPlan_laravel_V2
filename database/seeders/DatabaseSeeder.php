<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Expense;
use App\Models\WeddingDate;
use App\Models\WeddingSponsor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Categories ──────────────────────────────────────────────
        $categories = [
            ['Connaissance',         '#3498db', 'fas fa-handshake',            1],
            ['Dot',                  '#9b59b6', 'fas fa-gift',                 2],
            ['Mariage civil',        '#e74c3c', 'fas fa-landmark',             3],
            ['Bénédiction nuptiale', '#2ecc71', 'fas fa-church',               4],
            ['Logistique',           '#1abc9c', 'fas fa-truck',                5],
            ['Réception',            '#f39c12', 'fas fa-champagne-glasses',    6],
            ['Coût indirect',        '#95a5a6', 'fas fa-triangle-exclamation', 7],
        ];

        foreach ($categories as [$name, $color, $icon, $order]) {
            Category::firstOrCreate(['name' => $name], [
                'color'         => $color,
                'icon'          => $icon,
                'display_order' => $order,
            ]);
        }

        $cats = Category::orderBy('display_order')->pluck('id')->toArray();

        // ── Admin user ──────────────────────────────────────────────
        $admin = User::firstOrCreate(['username' => 'Administrateur'], [
            'email'     => 'admin@gmail.com',
            'password'  => Hash::make('Admin@1312'),
            'full_name' => 'Administrateur Principal',
            'role'      => 'admin',
        ]);

        // ── Wedding date ────────────────────────────────────────────
        $wedding = WeddingDate::firstOrCreate(['user_id' => $admin->id], [
            'fiance_nom_complet'  => 'Pierre-Jean',
            'fiancee_nom_complet' => 'Marie-Claire',
            'budget_total'        => 1_500_000.00,
            'wedding_date'        => now()->addMonths(6)->toDateString(),
        ]);

        // ── Demo Sponsor ────────────────────────────────────────────
        WeddingSponsor::firstOrCreate(['email' => 'sponsor@gmail.com'], [
            'wedding_dates_id'             => $wedding->id,
            'sponsor_nom_complet'          => 'Jonas A',
            'sponsor_conjoint_nom_complet' => 'Marie AG',
            'password_hash'                => password_hash('Sponsor@123', PASSWORD_DEFAULT),
            'telephone'                    => '+2290197000000',
            'role'                         => 'parrain',
            'statut'                       => 'actif',
        ]);

        // ── Demo Expenses ───────────────────────────────────────────
        if (Expense::where('user_id', $admin->id)->count() === 0) {
            $expenses = [
                // Connaissance
                [$cats[0], 'Enveloppe symbolique',        2,  2000, 1, false],
                [$cats[0], 'Boissons (jus de raisins)',   2,  5000, 1, false],
                [$cats[0], 'Déplacement',                 1,  5000, 1, false],
                // Dot
                [$cats[1], 'Bible',                       1,  6000, 1, false],
                [$cats[1], 'Valise',                      1, 10000, 1, false],
                [$cats[1], 'Pagne vlisco demi-pièce',     2, 27000, 1, false],
                [$cats[1], 'Pagne côte d\'ivoire',        5,  6500, 1, false],
                [$cats[1], 'Pagne Ghana demi-pièce',      4,  6500, 1, false],
                [$cats[1], 'Ensemble chaînes',            3,  3000, 1, false],
                [$cats[1], 'Chaussures',                  3,  3000, 1, false],
                [$cats[1], 'Sac à main',                  2,  3500, 1, false],
                [$cats[1], 'Montre et bracelet',          2,  3000, 1, false],
                [$cats[1], 'Série de bols',               3,  5500, 1, false],
                [$cats[1], 'Assiettes verre (demi-doz.)', 2,  4800, 1, false],
                [$cats[1], 'Série de casseroles',         1,  7000, 1, false],
                [$cats[1], 'Marmites (1-3 kg)',           1, 11000, 1, false],
                [$cats[1], 'Ustensiles de cuisine',       1,  8000, 1, false],
                [$cats[1], 'Gaz + accessoires',           1, 25000, 1, false],
                [$cats[1], 'Enveloppe fille',             1,100000, 1, false],
                [$cats[1], 'Enveloppe famille',           1, 25000, 1, false],
                [$cats[1], 'Collation spirituelle',       1, 45000, 1, false],
                // Mairie
                [$cats[2], 'Frais dossier mairie',        1, 50000, 1, false],
                [$cats[2], 'Petite réception mairie',     1, 50000, 1, false],
                // Église
                [$cats[3], 'Robe de mariée',              1, 20000, 1, false],
                [$cats[3], 'Costume marié',               1, 25000, 1, false],
                [$cats[3], 'Alliances',                   1, 15000, 1, false],
                [$cats[3], 'Tenues cortège (homme)',      3, 15000, 1, false],
                [$cats[3], 'Tenues cortège (femme)',      4, 15000, 1, false],
                // Logistique
                [$cats[4], 'Location de salle',           1,150000, 1, false],
                [$cats[4], 'Location de véhicule',        2, 35000, 1, false],
                [$cats[4], 'Carburant',                  20,   680, 1, false],
                [$cats[4], 'Prise de vue photo/vidéo',   1, 30000, 1, false],
                [$cats[4], 'Sonorisation',                1, 20000, 1, false],
                [$cats[4], 'Conception flyers',           1,  2000, 1, false],
                // Réception
                [$cats[5], 'Boissons (200 personnes)',  200,   600, 1, false],
                [$cats[5], 'Poulets',                    30,  2500, 1, false],
                [$cats[5], 'Porc',                        1, 30000, 1, false],
                [$cats[5], 'Poissons',                    2, 35000, 1, false],
                [$cats[5], 'Sacs de riz',                 1, 32000, 1, false],
                [$cats[5], 'Ingrédients cuisine',         1, 30000, 1, false],
                [$cats[5], 'Gâteau de mariage',           1, 25000, 1, false],
                // Coût indirect
                [$cats[6], 'Imprévus divers',             1, 75000, 1, false],
            ];

            foreach ($expenses as [$catId, $name, $qty, $price, $freq, $paid]) {
                Expense::create([
                    'user_id'      => $admin->id,
                    'category_id'  => $catId,
                    'name'         => $name,
                    'quantity'     => $qty,
                    'unit_price'   => $price,
                    'frequency'    => $freq,
                    'paid'         => $paid,
                    'payment_date' => $paid ? now()->toDateString() : null,
                ]);
            }
        }
    }
}
