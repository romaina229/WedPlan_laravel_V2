<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\WeddingDate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExportController extends Controller
{
    /**
     * Export des dépenses en CSV
     * GET /api/export/csv?filter=all|paid|unpaid
     */
    public function exportCsv(Request $request): Response
    {
        $userId  = $request->user()->id;
        $filter  = $request->query('filter', 'all');

        // Récupérer les dépenses avec catégorie
        $query = Expense::with('category')->where('user_id', $userId);

        if ($filter === 'paid') {
            $query->where('paid', true);
        } elseif ($filter === 'unpaid') {
            $query->where('paid', false);
        }

        $expenses = $query->orderBy('category_id')->orderBy('id')->get();

        // Infos mariage pour l'en-tête
        $wedding = WeddingDate::where('user_id', $userId)->first();

        // Calculs totaux
        $totalAmount = $expenses->sum(fn($e) => $e->quantity * $e->unit_price * $e->frequency);
        $totalPaid   = $expenses->where('paid', true)->sum(fn($e) => $e->quantity * $e->unit_price * $e->frequency);
        $totalUnpaid = $totalAmount - $totalPaid;

        // ── Construire le CSV ────────────────────────────────────────
        $rows = [];

        // En-tête du document
        $rows[] = ['WedPlan — Export Budget Mariage'];
        if ($wedding) {
            $rows[] = ['Mariés :', $wedding->fiance_nom_complet . ' & ' . $wedding->fiancee_nom_complet];
            $rows[] = ['Date :', $wedding->wedding_date ?? '—'];
            $rows[] = ['Budget total :', number_format($wedding->budget_total ?? 0, 0, ',', ' ') . ' FCFA'];
        }
        $rows[] = ['Exporté le :', now()->format('d/m/Y H:i')];
        $rows[] = ['Filtre :', match($filter) {
            'paid'   => 'Payées uniquement',
            'unpaid' => 'Non payées uniquement',
            default  => 'Toutes les dépenses',
        }];
        $rows[] = []; // Ligne vide

        // En-têtes colonnes
        $rows[] = [
            'Catégorie',
            'Description',
            'Quantité',
            'Prix unitaire (FCFA)',
            'Fréquence',
            'Montant total (FCFA)',
            'Statut',
            'Date de paiement',
            'Notes',
        ];

        // Données
        foreach ($expenses as $e) {
            $montantTotal = $e->quantity * $e->unit_price * $e->frequency;
            $rows[] = [
                $e->category?->name ?? 'Sans catégorie',
                $e->name,
                $e->quantity,
                number_format((float) $e->unit_price, 0, ',', ' '),
                $e->frequency,
                number_format($montantTotal, 0, ',', ' '),
                $e->paid ? 'Payée' : 'En attente',
                $e->payment_date?->format('d/m/Y') ?? '',
                $e->notes ?? '',
            ];
        }

        // Ligne vide + totaux
        $rows[] = [];
        $rows[] = ['TOTAUX', '', '', '', '', '', '', '', ''];
        $rows[] = ['Total général',  '', '', '', '', number_format($totalAmount, 0, ',', ' ') . ' FCFA', '', '', ''];
        $rows[] = ['Total payé',     '', '', '', '', number_format($totalPaid,   0, ',', ' ') . ' FCFA', '', '', ''];
        $rows[] = ['Reste à payer',  '', '', '', '', number_format($totalUnpaid, 0, ',', ' ') . ' FCFA', '', '', ''];
        $rows[] = ['Nombre articles','', '', '', '', $expenses->count(), '', '', ''];

        // ── Encoder en CSV ───────────────────────────────────────────
        $output = '';
        // BOM UTF-8 pour Excel
        $output .= "\xEF\xBB\xBF";

        foreach ($rows as $row) {
            $escaped = array_map(function ($cell) {
                $cell = (string) $cell;
                // Échapper les guillemets et encapsuler si nécessaire
                if (str_contains($cell, '"') || str_contains($cell, ',') || str_contains($cell, "\n")) {
                    $cell = '"' . str_replace('"', '""', $cell) . '"';
                }
                return $cell;
            }, $row);
            $output .= implode(';', $escaped) . "\r\n";
        }

        $filename = 'wedplan_budget_' . $filter . '_' . now()->format('Y-m-d') . '.csv';

        return response($output, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }
}