<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Categorie; // added model import

class CategorieController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // BEGIN: implemented listing
        $categories = Categorie::all();
        return response()->json($categories);
        // END
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        // BEGIN: API-friendly placeholder (use a Blade view if desired)
        return response()->json(['message' => 'Create form not implemented for API'], 501);
        // END
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // BEGIN: validate and create
        $validated = $request->validate([
            'nom_categorie' => 'required|string|max:255|unique:categories,nom_categorie',
        ]);

        $categorie = Categorie::create($validated);
        return response()->json($categorie, 201);
        // END
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // BEGIN: fetch single category
        $categorie = Categorie::findOrFail($id);
        return response()->json($categorie);
        // END
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        // BEGIN: API-friendly placeholder (use a Blade view if desired)
        return response()->json(['message' => 'Edit form not implemented for API'], 501);
        // END
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // BEGIN: validate and update
        $validated = $request->validate([
            'nom_categorie' => 'required|string|max:255|unique:categories,nom_categorie,' . $id,

        ]);

        $categorie = Categorie::findOrFail($id);
        $categorie->update($validated);

        return response()->json($categorie);
        // END
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // BEGIN: delete category
        $categorie = Categorie::findOrFail($id);
        $categorie->delete();

        return response()->json(null, 204);
        // END
    }
}
