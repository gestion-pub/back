<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return Role::with('permissions')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:roles',
            'permissions' => 'sometimes|array'
        ]);

        $role = Role::create($data);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return $role->load('permissions');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => 'required',
            'slug' => 'required|unique:roles,slug,' . $role->id,
            'permissions' => 'sometimes|array'
        ]);

        $role->update($data);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return $role->load('permissions');
    }

    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(null, 204);
    }

    public function attachPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'array'
        ]);

        $role->permissions()->sync($request->permissions);

        return response()->json(['message' => 'Permissions updated']);
    }
}
