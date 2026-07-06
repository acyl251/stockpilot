<?php

use App\Http\Controllers\API\ActivityLogController;
use App\Http\Controllers\API\AlertController;
use App\Http\Controllers\API\CommandeFournisseurController;
use App\Http\Controllers\API\PlanController;
use App\Http\Controllers\API\ConsommationController;
use App\Http\Controllers\API\FournisseurController;
use App\Http\Controllers\API\PublicMenuController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\SupplementController;
use App\Http\Controllers\API\TableController;
use App\Http\Controllers\API\SuperAdminController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ClientController;
use App\Http\Controllers\API\CompositionController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\DemoRequestController;
use App\Http\Controllers\API\OnboardingController;
use App\Http\Controllers\API\OrganisationController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\ProductTypeController;
use App\Http\Controllers\API\SaleController;
use App\Http\Controllers\API\StockMovementController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\PointDeVenteController;
use App\Http\Controllers\API\TransfertController;
use App\Http\Controllers\API\ChaineController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\EmailVerificationController;
use Illuminate\Support\Facades\Route;

// ─── Debug (temporaire) ───────────────────────────────────────────────────────
Route::get('/test-mail', function () {
    try {
        \Mail::raw('Test StockPilot', function ($m) {
            $m->to('test@test.tn')->subject('Test Mailtrap');
        });
        return 'Email envoyé avec succès !';
    } catch (\Exception $e) {
        return 'Erreur : ' . $e->getMessage();
    }
});

Route::get('/debug-env', function () {
    return response()->json([
        'getenv_scheduler'  => getenv('SCHEDULER_SECRET') ?: 'ABSENT',
        'env_scheduler'     => env('SCHEDULER_SECRET') ?: 'ABSENT',
        'server_scheduler'  => $_SERVER['SCHEDULER_SECRET'] ?? 'ABSENT',
        'env_var_scheduler' => $_ENV['SCHEDULER_SECRET'] ?? 'ABSENT',
        'getenv_mail_user'  => getenv('MAIL_USERNAME') ?: 'ABSENT',
        'getenv_app_env'    => getenv('APP_ENV') ?: 'ABSENT',
        'getenv_jwt'        => getenv('JWT_SECRET') ? 'PRESENT' : 'ABSENT',
        'config_cached'     => file_exists(base_path('bootstrap/cache/config.php')) ? 'OUI' : 'NON',
    ]);
});

Route::get('/debug-time', function () {
    return [
        'server_time' => now()->toDateTimeString(),
        'timezone'    => config('app.timezone'),
        'today'       => today()->toDateString(),
    ];
});

// ─── Public ───────────────────────────────────────────────────────────────────
Route::get('/scheduler/run', function (\Illuminate\Http\Request $request) {
    $secret = getenv('SCHEDULER_SECRET') ?: env('SCHEDULER_SECRET', '');
    if (empty($secret) || $request->get('token') !== $secret) {
        return response()->json([
            'error' => 'Unauthorized',
            'debug' => empty($secret) ? 'secret_not_set' : 'token_mismatch',
        ], 401);
    }
    \Artisan::call('schedule:run');
    return response()->json([
        'success' => true,
        'time'    => now()->toDateTimeString(),
    ]);
});

Route::post('/auth/login',         [AuthController::class, 'login']);
Route::post('/demo-request',       [DemoRequestController::class, 'store']);
Route::get('/verify-email/{token}', EmailVerificationController::class);
Route::get('/plans',               [SuperAdminController::class, 'plans']);
Route::get('/public/menu/{slug}',  [PublicMenuController::class, 'show']);

// ─── Authenticated Tenant Routes ──────────────────────────────────────────────
Route::middleware('auth.tenant')->group(function () {

    // Auth
    Route::prefix('auth')->group(function () {
        Route::post('logout',  [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me',       [AuthController::class, 'me']);
    });

    // Recherche globale
    Route::get('/search', SearchController::class);

    // Dashboard
    Route::get('/dashboard',                    [DashboardController::class, 'index']);
    Route::get('/dashboard/forecast/{product}', [DashboardController::class, 'forecast']);
    Route::get('/dashboard/restaurant',         [DashboardController::class, 'restaurant']);

    // Onboarding (AI-assisted setup + checklist)
    Route::get('/onboarding/checklist',         [OnboardingController::class, 'checklist']);
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

    // Tables & Commandes (secteur restauration uniquement)
    Route::get('/tables',                       [TableController::class, 'index']);
    Route::post('/tables',                      [TableController::class, 'store']);
    Route::patch('/tables/{id}',                [TableController::class, 'update']);
    Route::delete('/tables/{id}',               [TableController::class, 'destroy']);

    Route::get('/orders',                       [OrderController::class, 'index']);
    Route::post('/orders',                      [OrderController::class, 'store']);
    Route::get('/orders/{id}',                  [OrderController::class, 'show']);
    Route::patch('/orders/{id}/items',          [OrderController::class, 'updateItems']);
    Route::post('/orders/{id}/send-kitchen',    [OrderController::class, 'sendKitchen']);
    Route::post('/orders/{id}/pay',             [OrderController::class, 'pay']);
    Route::get('/orders/{id}/check-ingredients', [OrderController::class, 'checkIngredients']);

    // Suppléments (secteur restauration uniquement)
    Route::get('/supplements',      [SupplementController::class, 'index']);
    Route::post('/supplements',     [SupplementController::class, 'store']);
    Route::patch('/supplements/{id}', [SupplementController::class, 'update']);
    Route::delete('/supplements/{id}', [SupplementController::class, 'destroy']);

    // Consommation ingrédients (restauration)
    Route::get('/consommation',        [ConsommationController::class, 'index']);
    Route::get('/consommation/export', [ConsommationController::class, 'export']);

    // Recettes / Fiches techniques (secteur restauration uniquement)
    Route::get('/products/{product}/composition',              [CompositionController::class, 'index']);
    Route::post('/products/{product}/composition',             [CompositionController::class, 'store']);
    Route::patch('/products/{product}/composition/{composition}', [CompositionController::class, 'update']);
    Route::delete('/products/{product}/composition/{composition}', [CompositionController::class, 'destroy']);

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
    Route::get('/sales',                        [SaleController::class, 'index']);
    Route::get('/sales/export',                 [SaleController::class, 'export']);
    Route::post('/sales/check-ingredients',     [SaleController::class, 'checkIngredients']);
    Route::post('/sales',                       [SaleController::class, 'store']);
    Route::get('/sales/{id}',         [SaleController::class, 'show']);
    Route::get('/sales/{id}/invoice', [SaleController::class, 'invoice']);
    Route::post('/sales/{id}/cancel', [SaleController::class, 'cancel']);

    // Alerts & AI
    Route::prefix('alerts')->group(function () {
        Route::get('stock',               [AlertController::class, 'stockAlerts']);
        Route::get('suggestions',         [AlertController::class, 'aiSuggestions']);
        Route::get('anomalies',           [AlertController::class, 'anomalies']);
        Route::post('notify',             [AlertController::class, 'notifyStock']);
        Route::get('commandes-suggerees', [AlertController::class, 'commandesSuggerees']);
    });

    // Fournisseurs
    Route::get('/fournisseurs',       [FournisseurController::class, 'index']);
    Route::post('/fournisseurs',      [FournisseurController::class, 'store']);
    Route::patch('/fournisseurs/{id}', [FournisseurController::class, 'update']);
    Route::delete('/fournisseurs/{id}', [FournisseurController::class, 'destroy']);

    // Commandes fournisseur
    Route::get('/commandes-fournisseur',                    [CommandeFournisseurController::class, 'index']);
    Route::post('/commandes-fournisseur',                   [CommandeFournisseurController::class, 'store']);
    Route::get('/commandes-fournisseur/{id}',               [CommandeFournisseurController::class, 'show']);
    Route::patch('/commandes-fournisseur/{id}',             [CommandeFournisseurController::class, 'update']);
    Route::post('/commandes-fournisseur/{id}/envoyer',      [CommandeFournisseurController::class, 'envoyer']);
    Route::post('/commandes-fournisseur/{id}/receptionner', [CommandeFournisseurController::class, 'receptionner']);
    Route::delete('/commandes-fournisseur/{id}',            [CommandeFournisseurController::class, 'destroy']);

    // Plan usage
    Route::get('/plan/usage', [PlanController::class, 'usage']);

    // Activity Logs (admin + manager only — enforced in controller)
    Route::get('/activity-logs',        [ActivityLogController::class, 'index']);
    Route::get('/activity-logs/export', [ActivityLogController::class, 'export']);

    // User Management
    Route::get('/users',          [UserController::class, 'index']);
    Route::post('/users',         [UserController::class, 'store']);
    Route::patch('/users/{id}',   [UserController::class, 'update']);
    Route::delete('/users/{id}',  [UserController::class, 'destroy']);

    // Points de vente
    Route::get('/points-de-vente',                         [PointDeVenteController::class, 'index']);
    Route::post('/points-de-vente',                        [PointDeVenteController::class, 'store']);
    Route::patch('/points-de-vente/{id}',                  [PointDeVenteController::class, 'update']);
    Route::delete('/points-de-vente/{id}',                 [PointDeVenteController::class, 'destroy']);
    Route::get('/points-de-vente/{id}/stock',              [PointDeVenteController::class, 'stock']);
    Route::post('/points-de-vente/transfer',               [PointDeVenteController::class, 'transfer']);

    // Transferts inter-PDV
    Route::get('/transferts',        [TransfertController::class, 'index']);
    Route::get('/transferts/{id}',   [TransfertController::class, 'show']);
    Route::post('/transferts',       [TransfertController::class, 'store']);

    // Dashboard Chaîne (admin uniquement, multi-PDV)
    Route::prefix('chaine')->group(function () {
        Route::get('ca-par-point',      [ChaineController::class, 'caParPoint']);
        Route::get('stock-par-point',   [ChaineController::class, 'stockParPoint']);
        Route::get('top-plats',         [ChaineController::class, 'topPlats']);
        Route::get('transferts-recents',[ChaineController::class, 'transfertsRecents']);
    });

    // Super-Admin Platform Dashboard
    Route::middleware('super.admin')->prefix('super-admin')->group(function () {
        Route::get('/dashboard',        [SuperAdminController::class, 'dashboard']);
        Route::get('/users',            [SuperAdminController::class, 'users']);
        Route::get('/organisations',    [SuperAdminController::class, 'organisations']);
        Route::post('/organisations',   [SuperAdminController::class, 'createOrganisation']);
        Route::get('/plans',            [SuperAdminController::class, 'plans']);
        Route::patch('/users/{id}',          [SuperAdminController::class, 'updateUser']);
        Route::delete('/users/{id}',         [SuperAdminController::class, 'destroyUser']);
        Route::patch('/organisations/{id}',  [SuperAdminController::class, 'updateOrganisation']);
        Route::delete('/organisations/{id}', [SuperAdminController::class, 'destroyOrganisation']);
        Route::get('/demo-requests',        [DemoRequestController::class, 'index']);
        Route::patch('/demo-requests/{id}', [DemoRequestController::class, 'updateStatus']);
    });
});
