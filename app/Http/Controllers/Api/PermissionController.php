<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index()
    {
        return Permission::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:permissions'
        ]);

        return Permission::create($data);
    }
}
