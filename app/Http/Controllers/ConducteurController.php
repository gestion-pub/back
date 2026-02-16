<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conducteur;
use App\Models\ConducteurSlot;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ConducteurExport;
use Illuminate\Support\Facades\Log;

class ConducteurController extends Controller
{
    public function index()
    {
        $conducteurs = Conducteur::with('slots.campagne.client', 'slots.campagne.categorie')->latest()->get();
        return response()->json($conducteurs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'status' => 'required|in:draft,published',
            'slots' => 'array',
            'slots.*.time_slot' => 'required|string',
            'slots.*.campagne_id' => 'nullable|exists:campagnes,id',
        ]);

        $conducteur = Conducteur::create([
            'name' => $validated['name'],
            'date' => $validated['date'],
            'status' => $validated['status'],
        ]);

        if (isset($validated['slots'])) {
            foreach ($validated['slots'] as $slotData) {
                $conducteur->slots()->create($slotData);
            }
        }

        return response()->json($conducteur->load('slots.campagne'), 201);
    }

    public function show($id)
    {
        $conducteur = Conducteur::with('slots.campagne.client', 'slots.campagne.categorie')->findOrFail($id);
        return response()->json($conducteur);
    }

    public function update(Request $request, $id)
    {
        $conducteur = Conducteur::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string|max:255',
            'date' => 'date',
            'status' => 'in:draft,published',
            'slots' => 'array',
            'slots.*.id' => 'nullable|exists:conducteur_slots,id',
            'slots.*.time_slot' => 'required|string',
            'slots.*.campagne_id' => 'nullable|exists:campagnes,id',
        ]);

        $conducteur->update($validated);

        if (isset($validated['slots'])) {
            // Delete existing slots
            $conducteur->slots()->delete();

            // Create new slots
            foreach ($validated['slots'] as $slotData) {
                $conducteur->slots()->create($slotData);
            }
        }

        return response()->json($conducteur->load('slots.campagne'));
    }

    public function destroy($id)
    {
        $conducteur = Conducteur::findOrFail($id);
        $conducteur->delete();
        return response()->json(null, 204);
    }

    public function export($id)
    {
        try {
            return Excel::download(new ConducteurExport($id), 'conducteur.xlsx');
        } catch (\Exception $e) {
            Log::error('Conducteur Export Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
