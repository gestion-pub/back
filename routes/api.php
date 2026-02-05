<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\CampagneController;
use App\Http\Controllers\CategorieController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});


Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});


Route::middleware(['auth:sanctum', 'permission:view_sales'])->get('/ventes', function () {
    return Vente::all();
});

Route::middleware(['auth:sanctum', 'permission:create_sales'])->post('/ventes', function () {
    // create vente
});
Route::get('/roles', [RoleController::class, 'index']);
Route::resource('categories', CategorieController::class);
Route::resource('clients', ClientController::class);
Route::resource('campagnes', CampagneController::class);
Route::resource('conducteurs', App\Http\Controllers\ConducteurController::class);
Route::resource('plannings', App\Http\Controllers\PlanningController::class);


Route::middleware(['auth:sanctum', 'permission:manage_roles'])->group(function () {

    Route::post('/roles', [RoleController::class, 'store']);
    Route::post('/roles/{role}/permissions', [RoleController::class, 'attachPermissions']);

    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::post('/permissions', [PermissionController::class, 'store']);

});
Route::post('/users', [\App\Http\Controllers\Auth\AuthController::class, 'createUser']);
Route::put('/users/{id}', [\App\Http\Controllers\Auth\AuthController::class, 'updateUser']);
Route::delete('/users/{id}', [\App\Http\Controllers\Auth\AuthController::class, 'deleteUser']);
Route::get('/users', function () {
    return \App\Models\User::select('id', 'name', 'email', 'role')->get();
});