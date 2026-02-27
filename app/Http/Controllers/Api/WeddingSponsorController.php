<?php

namespace App\Http\Controllers;

use App\Models\WeddingSponsor;
use App\Models\WeddingDate;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WeddingSponsorController extends Controller
{
    /**
     * Display a listing of the sponsors.
     */
    public function index(Request $request): View
    {
        $query = WeddingSponsor::with('weddingDate');
        
        // Filter by wedding date if specified
        if ($request->has('wedding_date_id')) {
            $query->where('wedding_dates_id', $request->wedding_date_id);
        }
        
        // Filter by role
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }
        
        // Filter by status
        if ($request->has('statut') && $request->statut !== 'all') {
            $query->where('statut', $request->statut);
        }
        
        $sponsors = $query->orderBy('created_at', 'desc')->paginate(15);
        $weddingDates = WeddingDate::all();
        
        return view('wedding-sponsors.index', compact('sponsors', 'weddingDates'));
    }

    /**
     * Show the form for creating a new sponsor.
     */
    public function create(Request $request): View
    {
        $weddingDates = WeddingDate::all();
        $selectedWeddingDateId = $request->get('wedding_date_id');
        
        return view('wedding-sponsors.create', compact('weddingDates', 'selectedWeddingDateId'));
    }

    /**
     * Store a newly created sponsor in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'wedding_dates_id' => 'required|exists:wedding_dates,id',
            'sponsor_nom_complet' => 'required|string|max:255',
            'sponsor_conjoint_nom_complet' => 'nullable|string|max:255',
            'email' => 'required|email|unique:wedding_sponsors,email',
            'password' => 'required|string|min:6',
            'telephone' => 'nullable|string|max:20',
            'role' => 'required|in:parrain,temoin,parent,autre',
            'statut' => 'required|in:actif,inactif,en_attente',
            'commentaires' => 'nullable|string',
        ]);

        // Hash the password
        $validated['password_hash'] = $validated['password'];
        unset($validated['password']);

        WeddingSponsor::create($validated);

        return redirect()
            ->route('wedding-sponsors.index')
            ->with('success', 'Sponsor créé avec succès.');
    }

    /**
     * Display the specified sponsor.
     */
    public function show(WeddingSponsor $weddingSponsor): View
    {
        $weddingSponsor->load('weddingDate');
        return view('wedding-sponsors.show', compact('weddingSponsor'));
    }

    /**
     * Show the form for editing the specified sponsor.
     */
    public function edit(WeddingSponsor $weddingSponsor): View
    {
        $weddingDates = WeddingDate::all();
        return view('wedding-sponsors.edit', compact('weddingSponsor', 'weddingDates'));
    }

    /**
     * Update the specified sponsor in storage.
     */
    public function update(Request $request, WeddingSponsor $weddingSponsor): RedirectResponse
    {
        $validated = $request->validate([
            'wedding_dates_id' => 'required|exists:wedding_dates,id',
            'sponsor_nom_complet' => 'required|string|max:255',
            'sponsor_conjoint_nom_complet' => 'nullable|string|max:255',
            'email' => 'required|email|unique:wedding_sponsors,email,' . $weddingSponsor->id,
            'password' => 'nullable|string|min:6',
            'telephone' => 'nullable|string|max:20',
            'role' => 'required|in:parrain,temoin,parent,autre',
            'statut' => 'required|in:actif,inactif,en_attente',
            'commentaires' => 'nullable|string',
        ]);

        // Update password only if provided
        if (!empty($validated['password'])) {
            $validated['password_hash'] = $validated['password'];
        }
        unset($validated['password']);

        $weddingSponsor->update($validated);

        return redirect()
            ->route('wedding-sponsors.index')
            ->with('success', 'Sponsor mis à jour avec succès.');
    }

    /**
     * Remove the specified sponsor from storage.
     */
    public function destroy(WeddingSponsor $weddingSponsor): RedirectResponse
    {
        $weddingSponsor->delete();

        return redirect()
            ->route('wedding-sponsors.index')
            ->with('success', 'Sponsor supprimé avec succès.');
    }

    /**
     * Toggle sponsor status.
     */
    public function toggleStatus(WeddingSponsor $weddingSponsor): RedirectResponse
    {
        $weddingSponsor->statut = $weddingSponsor->statut === 'actif' ? 'inactif' : 'actif';
        $weddingSponsor->save();

        return redirect()
            ->back()
            ->with('success', 'Statut du sponsor modifié avec succès.');
    }
}