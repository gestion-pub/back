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
        $validated = $request->validate([
            'date_début' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_début',
            'type' => 'required|in:classique,hor_écran',
            'ranking' => 'required|integer|in:0,1',
            'duree' => 'nullable|integer|min:1',
            'spot' => 'required|string|max:255',
            'spot_id' => 'nullable|integer',
            'id_client' => 'required|exists:clients,id',
            'id_categorie' => 'required|exists:categories,id',
        ]);

        // Map frontend field names to database column names
        $campagneData = [
            'date_debut' => $validated['date_début'],
            'date_fin' => $validated['date_fin'],
            'type' => $validated['type'],
            'ranking' => $validated['ranking'],
            'duree' => $validated['duree'] ?? 0,
            'spot' => $validated['spot'],
            'spot_id' => $validated['spot_id'] ?? null,
            'id_client' => $validated['id_client'],
            'id_categorie' => $validated['id_categorie'],
        ];

        $campagne = Campagne::create($campagneData);

        return response()->json($campagne, 201);
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
        $request->validate([
            'date_début' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_début',
            'type' => 'required|in:classique,hors_ecran',
            'ranking' => 'required|in:active,non_active',
            'duree' => 'required|integer|min:1',
            'spot' => 'required|string|max:255',
            'id_client' => 'required|exists:clients,id',
            'id_categorie' => 'required|exists:categories,id',
        ]);

        $campagne->update($request->all());

        return response()->json($campagne);
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
        $campagne->delete();

        return response()->json(['message' => 'Campagne supprimée !']);

        //return redirect()->route('campagnes.index')->with('success', 'Campagne supprimée !');

    }

}
