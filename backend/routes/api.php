<?php

use App\Http\Controllers\API\AlertController;
use App\Http\Controllers\API\SuperAdminController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\DemoRequestController;
use App\Http\Controllers\API\OnboardingController;
use App\Http\Controllers\API\OrganisationController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProductTypeController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\StockMovementController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

// ─── Public ───────────────────────────────────────────────────────────────────
Route::post('/auth/login',    [AuthController::class, 'login']);
Route::post('/demo-request',  [DemoRequestController::class, 'store']);
Route::get('/plans',          [SuperAdminController::class, 'plans']);

// ─── Authenticated Tenant Routes ──────────────────────────────────────────────
Route::middleware('auth.tenant')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout',  [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me',       [AuthController::class, 'me']);
    });

    // Dashboard
    Route::get('/dashboard',                    [DashboardController::class, 'index']);
    Route::get('/dashboard/forecast/{product}', [DashboardController::class, 'forecast']);

    // Onboarding (AI-assisted setup)
    Route::post('/onboarding/suggest',          [OnboardingController::class, 'suggest']);
    Route::post('/onboarding/suggest-products', [OnboardingController::class, 'suggestProducts']);
    Route::post('/onboarding/confirm',          [OnboardingController::class, 'confirm']);

    // Catalogue Configuration
    Route::apiResource('categories',    CategoryController::class)->except(['show']);
    Route::apiResource('product-types', ProductTypeController::class);

    // Product Catalogue
    // Specific route must be declared BEFORE the resource so it isn't captured by products/{product}.
    Route::get('/products/check-reference', [ProductController::class, 'checkReference']);
    Route::apiResource('products', ProductController::class);

    // Stock Movements
    Route::get('/movements',      [StockMovementController::class, 'index']);
    Route::post('/movements',     [StockMovementController::class, 'store']);
    Route::get('/movements/{id}', [StockMovementController::class, 'show']);

    // Clients (comptes / crédit)
    Route::get('/clients',           [ClientController::class, 'index']);
    Route::post('/clients',          [ClientController::class, 'store']);
    Route::get('/clients/{id}',      [ClientController::class, 'show']);
    Route::patch('/clients/{id}',     [ClientController::class, 'update']);
    Route::post('/clients/{id}/pay',  [ClientController::class, 'pay']);
    Route::post('/clients/{id}/remind', [ClientController::class, 'remind']);

    // Organisation (infos légales / facturation)
    Route::get('/organisation',   [OrganisationController::class, 'show']);
    Route::patch('/organisation', [OrganisationController::class, 'update']);

    // Caisse (POS)
    Route::get('/sales',              [SaleController::class, 'index']);
    Route::get('/sales/export',       [SaleController::class, 'export']);
    Route::post('/sales',             [SaleController::class, 'store']);
    Route::get('/sales/{id}',         [SaleController::class, 'show']);
    Route::get('/sales/{id}/invoice', [SaleController::class, 'invoice']);
    Route::post('/sales/{id}/cancel', [SaleController::class, 'cancel']);

    // Alerts & AI
    Route::prefix('alerts')->group(function () {
        Route::get('stock',       [AlertController::class, 'stockAlerts']);
        Route::get('suggestions', [AlertController::class, 'aiSuggestions']);
        Route::get('anomalies',   [AlertController::class, 'anomalies']);
        Route::post('notify',     [AlertController::class, 'notifyStock']);
    });

    // User Management
    Route::get('/users',          [UserController::class, 'index']);
    Route::post('/users',         [UserController::class, 'store']);
    Route::patch('/users/{id}',   [UserController::class, 'update']);
    Route::delete('/users/{id}',  [UserController::class, 'destroy'])->middleware('super.admin');

    // Super-Admin Platform Dashboard
    Route::middleware('super.admin')->prefix('super-admin')->group(function () {
        Route::get('/dashboard',        [SuperAdminController::class, 'dashboard']);
        Route::get('/users',            [SuperAdminController::class, 'users']);
        Route::get('/organisations',    [SuperAdminController::class, 'organisations']);
        Route::post('/organisations',   [SuperAdminController::class, 'createOrganisation']);
        Route::get('/plans',            [SuperAdminController::class, 'plans']);
        Route::get('/demo-requests',        [DemoRequestController::class, 'index']);
        Route::patch('/demo-requests/{id}', [DemoRequestController::class, 'updateStatus']);
    });
});
