<?php

namespace App\Http\Controllers;

use App\Models\Campagne;
use App\Models\Client;
use App\Models\Categorie;
use Illuminate\Http\Request;


class CampagneController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    /* public function index()
     {
          $campagnes = Campagne::with(['client', 'categorie'])->latest()->get();
         return view('campagnes.index', compact('campagnes'));
     }*/

    public function index(Request $request)
    {
        $query = Campagne::with(['client', 'categorie']);

        // 🔍 Search
        if ($request->filled('search')) {
            $query->whereHas('client', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            })
                ->orWhere('type', 'like', "%{$request->search}%")
                ->orWhere('spot', 'like', "%{$request->search}%");
        }

        // 📂 Filter Category
        if ($request->filled('categorie_id')) {
            $query->where('categorie_id', $request->categorie_id);
        }

        //  Filter Type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        //  Filter Ranking
        if ($request->filled('ranking')) {
            $query->where('ranking', $request->ranking);
        }

        $campagnes = $query->latest()->get();

        // Pour afficher la liste déroulante des catégories
        $categories = Categorie::all();
        return response()->json($campagnes);

        // return view('campagnes.index', compact('campagnes', 'categories'));
    }



    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $clients = Client::all();
        $categories = Categorie::all();

        return response()->json(['clients' => $clients, 'categories' => $categories]);
        // return view('campagnes.create', compact('clients', 'categories'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $logFile = storage_path('logs/debug_campagne.log');
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Store Request: " . json_encode($request->all()) . PHP_EOL, FILE_APPEND);
        
        try {
            $validated = $request->validate([
                'date_debut' => 'required|date',
                'date_fin' => 'required|date|after_or_equal:date_debut',
                'type' => 'required|in:classique,hor_ecran',
                'ranking' => 'required|integer|in:0,1',
                'duree' => 'nullable|numeric',
                'spot' => 'nullable|string|max:255',
                'spot_id' => 'required|string',
                'id_client' => 'required|exists:clients,id',
                'id_categorie' => 'required|exists:categories,id',
                'passages' => 'nullable|array',
                'spots' => 'nullable|array',
            ]);

            $campagneData = [
                'date_debut' => $validated['date_debut'],
                'date_fin' => $validated['date_fin'],
                'type' => $validated['type'],
                'ranking' => $validated['ranking'],
                'duree' => $validated['duree'] ?? 0,
                'spot' => $validated['spot'] ?? 'Multi-spots',
                'spot_id' => $validated['spot_id'] ?? null,
                'id_client' => $validated['id_client'],
                'id_categorie' => $validated['id_categorie'],
                'spots' => $validated['spots'] ?? null,
                'created_by' => auth()->id(),
            ];

            $campagne = Campagne::create($campagneData);

            // Save passages if imported
            if (!empty($validated['passages'])) {
                foreach ($validated['passages'] as $p) {
                    $dateStr = $p['date'];
                    $actualDate = null;

                    // Handle relative dates from Excel analysis
                    $btnDate = \Carbon\Carbon::parse($validated['date_debut']);
                    
                    if (str_contains(strtolower($dateStr), 'samedi 28') || str_contains(strtolower($dateStr), 'sam')) {
                        // For the old format or new format 'sam'
                        // We'll try to find the actual Saturday. For simplicity, if it's 'sam', we'll assume it's related to the start date.
                        // Logic: Find the day index (0=dim, 1=lun, ..., 4=jeu, 5=ven, 6=sam)
                        $actualDate = $btnDate->copy();
                        if (str_contains(strtolower($dateStr), 'jeu')) { /* stay at start or calculate offset */ }
                        elseif (str_contains(strtolower($dateStr), 'ven')) { $actualDate->addDay(); }
                        elseif (str_contains(strtolower($dateStr), 'sam')) { $actualDate->addDays(2); }
                        elseif (str_contains(strtolower($dateStr), 'dim')) { $actualDate->addDays(3); }
                        elseif (str_contains(strtolower($dateStr), 'lun')) { $actualDate->addDays(4); }
                        
                        // BUT, if it's the specific old format 'Samedi 28', let's keep that logic
                        if (str_contains($dateStr, 'Samedi 28')) {
                            $actualDate = \Carbon\Carbon::parse($validated['date_debut']);
                        } elseif (str_contains($dateStr, 'Dimanche 1')) {
                            $actualDate = \Carbon\Carbon::parse($validated['date_debut'])->addDay();
                        }
                    } else {
                        // New format handles: jeu, ven, sam, dim, lun
                        $dayMap = ['jeu' => 0, 'ven' => 1, 'sam' => 2, 'dim' => 3, 'lun' => 4];
                        $found = false;
                        foreach($dayMap as $key => $offset) {
                            if (str_contains(strtolower($dateStr), $key)) {
                                $actualDate = $btnDate->copy()->addDays($offset);
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            try {
                                $actualDate = \Carbon\Carbon::parse($dateStr);
                            } catch (\Exception $e) {
                                $actualDate = $btnDate;
                            }
                        }
                    }

                    $heureStr = str_replace(['H', 'h'], ':', $p['heure']);
                    if (strlen($heureStr) === 5) {
                        $heureStr .= ':00';
                    }

                    $campagne->plannings()->create([
                        'date' => $actualDate,
                        'heure' => $heureStr,
                        'spot' => $p['spot'],
                        'duree' => $p['duree'] ?? 10,
                        'prix_HT' => 0,
                        'status' => 'réservé'
                    ]);
                }
            }

            return response()->json($campagne->load(['client', 'categorie']), 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Store Validation Errors: " . json_encode($e->errors()) . PHP_EOL, FILE_APPEND);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'request' => $request->all()
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Campagne $campagne)
    {
        $clients = Client::all();
        $categories = Categorie::all();

        return response()->json(['campagne' => $campagne, 'clients' => $clients, 'categories' => $categories]);
        //return view('campagnes.edit', compact('campagne', 'clients', 'categories'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Campagne $campagne)
    {
        $logFile = storage_path('logs/debug_campagne.log');
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Update Request (ID: " . $campagne->id . "): " . json_encode($request->all()) . PHP_EOL, FILE_APPEND);
 
        $today = \Carbon\Carbon::now()->startOfDay();
        $dateFin = \Carbon\Carbon::parse($campagne->date_fin)->startOfDay();
        if ($today->gt($dateFin)) {
            return response()->json(['message' => 'Access Denied: Impossible de modifier une campagne qui est déjà terminée.'], 403);
        }

        try {
            $validated = $request->validate([
                'date_debut' => 'required|date',
                'date_fin' => 'required|date|after_or_equal:date_debut',
                'type' => 'required|in:classique,hor_ecran',
                'ranking' => 'required|integer|in:0,1',
                'duree' => 'nullable|numeric',
                'spot' => 'nullable|string|max:255',
                'spot_id' => 'required|string',
                'id_client' => 'required|exists:clients,id',
                'id_categorie' => 'required|exists:categories,id',
                'passages' => 'nullable|array',
                'spots' => 'nullable|array',
            ]);
 
            $campagneData = [
                'date_debut' => $validated['date_debut'],
                'date_fin' => $validated['date_fin'],
                'type' => $validated['type'],
                'ranking' => $validated['ranking'],
                'duree' => $validated['duree'] ?? 0,
                'spot' => $validated['spot'] ?? $campagne->spot,
                'spot_id' => $validated['spot_id'] ?? $campagne->spot_id,
                'id_client' => $validated['id_client'],
                'id_categorie' => $validated['id_categorie'],
                'spots' => $request->spots ?? $campagne->spots,
            ];
 
            $campagne->update($campagneData);
 
            // Refresh passages if provided
            if (isset($validated['passages'])) {
                $campagne->plannings()->delete();
                foreach ($validated['passages'] as $p) {
                    $dateStr = $p['date'];
                    $actualDate = null;

                    // Handle relative dates from Excel analysis
                    $btnDate = \Carbon\Carbon::parse($validated['date_debut']);
                    
                    if (str_contains(strtolower($dateStr), 'samedi 28') || str_contains(strtolower($dateStr), 'sam')) {
                        $actualDate = $btnDate->copy();
                        if (str_contains(strtolower($dateStr), 'jeu')) { /* stay at start */ }
                        elseif (str_contains(strtolower($dateStr), 'ven')) { $actualDate->addDay(); }
                        elseif (str_contains(strtolower($dateStr), 'sam')) { $actualDate->addDays(2); }
                        elseif (str_contains(strtolower($dateStr), 'dim')) { $actualDate->addDays(3); }
                        elseif (str_contains(strtolower($dateStr), 'lun')) { $actualDate->addDays(4); }
                        
                        if (str_contains($dateStr, 'Samedi 28')) {
                            $actualDate = \Carbon\Carbon::parse($validated['date_debut']);
                        } elseif (str_contains($dateStr, 'Dimanche 1')) {
                            $actualDate = \Carbon\Carbon::parse($validated['date_debut'])->addDay();
                        }
                    } else {
                        $dayMap = ['jeu' => 0, 'ven' => 1, 'sam' => 2, 'dim' => 3, 'lun' => 4];
                        $found = false;
                        foreach($dayMap as $key => $offset) {
                            if (str_contains(strtolower($dateStr), $key)) {
                                $actualDate = $btnDate->copy()->addDays($offset);
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) {
                            try {
                                $actualDate = \Carbon\Carbon::parse($dateStr);
                            } catch (\Exception $e) {
                                $actualDate = $btnDate;
                            }
                        }
                    }

                    // Format time: convert '08H30' to '08:30:00'
                    $heureStr = str_replace(['H', 'h'], ':', $p['heure']);
                    if (strlen($heureStr) === 5) {
                        $heureStr .= ':00';
                    }

                    $campagne->plannings()->create([
                        'date' => $actualDate,
                        'heure' => $heureStr,
                        'spot' => $p['spot'],
                        'duree' => $p['duree'] ?? 10,
                        'prix_HT' => 0,
                        'status' => 'réservé'
                    ]);
                }
            }
 
            return response()->json($campagne->load(['client', 'categorie']));
 
        } catch (\Illuminate\Validation\ValidationException $e) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Update Validation Errors: " . json_encode($e->errors()) . PHP_EOL, FILE_APPEND);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'request' => $request->all()
            ], 422);
        }
        //return redirect()->route('campagnes.index')->with('success', 'Campagne modifiée avec succès !');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Campagne $campagne)
    {
        $today = \Carbon\Carbon::now()->startOfDay();
        $dateFin = \Carbon\Carbon::parse($campagne->date_fin)->startOfDay();
        if ($today->gt($dateFin)) {
            return response()->json(['message' => 'Access Denied: Impossible de supprimer une campagne qui est déjà terminée.'], 403);
        }

        $campagne->delete();

        return response()->json(['message' => 'Campagne supprimée !']);

        //return redirect()->route('campagnes.index')->with('success', 'Campagne supprimée !');

    }

}
