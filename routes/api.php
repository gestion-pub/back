<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\CampagneController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ExcelImportController;
use App\Http\Controllers\PlanningController;
use App\Http\Controllers\ConducteurController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// --- Routes Publiques ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/import-planning-colors', [\App\Http\Controllers\PlanningImportController::class, 'import']);
Route::post('/import-sheet', [\App\Http\Controllers\ImportController::class, 'importerDepuisLienPrive']);

// --- Routes Protégées (Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth & Profil
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/user/password', [AuthController::class, 'updatePassword']);

    // Plannings (Gestion & Importation)
    Route::get('/plannings/campaign/{id}', [PlanningController::class, 'getByCampaign']);
    Route::post('/plannings/bulk', [PlanningController::class, 'bulkStore']);
    // Assure-toi que le nom de la méthode est exactement celui-ci :
    Route::post('/plannings/analyze', [PlanningController::class, 'uploadAndAnalyze']);
    Route::get('/plannings/export/{campaignId}', [PlanningController::class, 'export']);
    
    
    // --- NOUVELLE ROUTE EXCEL IMPORT ---
    Route::post('/plannings/import', [ExcelImportController::class, 'import']);
    
    Route::resource('plannings', PlanningController::class);

    // Clients, Campagnes, Catégories
    Route::resource('categories', CategorieController::class);
    Route::resource('clients', ClientController::class);
    Route::resource('campagnes', CampagneController::class);

    // Conducteurs
    Route::resource('conducteurs', ConducteurController::class);
    Route::get('/conducteurs/{id}/export', [ConducteurController::class, 'export']);

    // Gestion des Utilisateurs (Admin)
    Route::get('/users', function () {
        return \App\Models\User::select('id', 'name', 'email', 'role')->get();
    });
    Route::post('/users', [AuthController::class, 'createUser']);
    Route::put('/users/{id}', [AuthController::class, 'updateUser']);
    Route::delete('/users/{id}', [AuthController::class, 'deleteUser']);
    Route::post('/users/{id}/avatar', [AuthController::class, 'uploadAvatar']);

    // Roles & Permissions
    Route::get('/roles', [RoleController::class, 'index']);
    Route::get('/permissions', [PermissionController::class, 'index']);
    
    Route::middleware(['permission:manage_roles'])->group(function () {
        Route::post('/roles', [RoleController::class, 'store']);
        Route::put('/roles/{role}', [RoleController::class, 'update']);
        Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
        Route::post('/roles/{role}/permissions', [RoleController::class, 'attachPermissions']);
        Route::post('/permissions', [PermissionController::class, 'store']);
    });

    // Exemple de routes avec permissions spécifiques
    Route::middleware(['permission:view_sales'])->get('/ventes', function () {
        return \App\Models\Vente::all(); // Assurez-vous que le modèle Vente existe
    });
});