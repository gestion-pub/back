<?php

namespace App\Http\Controllers;

use App\Models\Client; // NEW: import Client Eloquent model
use Illuminate\Validation\Rule; // NEW: import Rule for unique validation
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // added

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Fetch all clients (you can replace with paginate if needed)
        $clients = Client::orderBy('id', 'desc')->get(); // switched to Eloquent
        return response()->json($clients);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
   // Assuming you have an Eloquent model named 'Client'

public function store(Request $request)
{
    // 1. Validation remains the same
    $data = $request->validate([
        'name'          => 'required|string|max:255',
        'contact_name'  => 'nullable|string|max:255',
        'email'         => 'required|email|max:255|unique:clients,email',
        'campagne_nom'  => 'nullable|string|max:255',
        'adresse'       => 'required|string|max:500',
        'telephone'     => 'required|string|max:50',
    ]);

    // 2. Ensure non-null values for DB constraints (workaround if migration wasn't run)
    $data['campagne_nom'] = $data['campagne_nom'] ?? '-';

    // 3. Set creator
    $data['created_by'] = auth()->id();

    // 4. Eloquent Insertion
    $client = Client::create($data);

    // 3. Eloquent returns the created model, no second query needed
    
    // 4. Response
    return response()->json($client, 201);
}

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $client = Client::find($id); // use Eloquent instead of DB facade
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        return response()->json($client);
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
        // Ensure the client exists
        $client = Client::find($id); // use Eloquent instead of DB facade
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        // Validate only provided fields
        $data = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'contact_name'  => 'nullable|string|max:255',
            'email'         => ['sometimes', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($client->id)],
            'campagne_nom'  => 'nullable|string|max:255',
            'adresse'       => 'sometimes|string|max:500',
            'telephone'     => 'sometimes|string|max:50',
        ]);

        if (empty($data)) {
            return response()->json(['message' => 'No data to update'], 422);
        }

        // Ensure non-null values for DB constraints
        if (array_key_exists('campagne_nom', $data)) {
            $data['campagne_nom'] = $data['campagne_nom'] ?? '-';
        }

        // Eloquent handles updated_at automatically
        $client->fill($data)->save();

        $client = Client::find($id); // optional re-fetch; can also return $client directly
        return response()->json($client);
    }

    /**
     * Remove the specified resource from storage.            
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $client = Client::find($id); // use Eloquent instead of DB facade
        if (!$client) {
            return response()->json(['message' => 'Client not found'], 404);
        }
        $client->delete();

        return response()->noContent();
    }
}