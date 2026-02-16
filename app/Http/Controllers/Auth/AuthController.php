<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        return response()->json($user);
    }

    public function createUser(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|string'
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        // Sync with RBAC roles
        $role = Role::where('slug', $request->role)->first();
        if ($role) {
            $user->roles()->sync([$role->id]);
        }

        return response()->json($user);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'password' => 'nullable|min:6',
            'role' => 'sometimes|required'
        ]);

        $userData = [
            'name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
            'role' => $request->role ?? $user->role,
        ];

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        // Sync with RBAC roles if role was updated
        if ($request->has('role')) {
            $role = Role::where('slug', $request->role)->first();
            if ($role) {
                $user->roles()->sync([$role->id]);
            }
        }

        return response()->json($user);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        \Illuminate\Support\Facades\Log::info('Login attempt for: ' . $request->email);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            \Illuminate\Support\Facades\Log::warning('Login failed for: ' . $request->email);
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        \Illuminate\Support\Facades\Log::info('Login successful for: ' . $request->email);
        return $user->createToken('auth_token')->plainTextToken;
    }

    public function user(Request $request)
    {
        return $request->user();
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}