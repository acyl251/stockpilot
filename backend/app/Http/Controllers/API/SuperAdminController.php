<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Services\CatalogSeederService;
use App\Services\RestaurantCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    public function __construct(
        private CatalogSeederService $catalogSeeder,
        private RestaurantCategoryService $restaurantCategories,
    ) {}

    public function dashboard(): JsonResponse
    {
        $isOracle  = DB::connection()->getDriverName() === 'oracle';
        $dateTrunc = $isOracle ? 'TRUNC(created_at)' : "DATE(created_at)";

        // ── Tenants ─────────────────────────────────────────────────────────────
        $orgsActives   = Organisation::withoutGlobalScopes()->where('actif', true)->count();
        $orgsInactives = Organisation::withoutGlobalScopes()->where('actif', false)->count();
        $totalOrgs     = $orgsActives + $orgsInactives;
        $churnRate     = $totalOrgs > 0 ? round($orgsInactives / $totalOrgs * 100, 1) : 0;

        $newOrgsMois = Organisation::withoutGlobalScopes()
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $newOrgsMoisPrecedent = Organisation::withoutGlobalScopes()
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        // ── Revenue MRR / ARR ────────────────────────────────────────────────────
        $mrr = Organisation::withoutGlobalScopes()
            ->where('organisations.actif', true)
            ->join('plans', 'organisations.plan_id', '=', 'plans.id')
            ->sum('plans.prix_mensuel');

        $arr = round($mrr * 12, 3);
        $mrr = round($mrr, 3);

        // ── Plan distribution ────────────────────────────────────────────────────
        $planDistribution = Plan::withCount([
            'organisations as total'  => fn($q) => $q->withoutGlobalScopes(),
            'organisations as actifs' => fn($q) => $q->withoutGlobalScopes()->where('actif', true),
        ])->get(['id', 'nom', 'prix_mensuel', 'ia_activee']);

        // ── Users & products (platform-wide) ────────────────────────────────────
        $totalUsers = User::withoutGlobalScope(TenantScope::class)
            ->where('actif', true)
            ->count();

        $totalProduits = Product::withoutGlobalScope(TenantScope::class)
            ->where('actif', true)
            ->count();

        $totalMouvements = StockMovement::withoutGlobalScope(TenantScope::class)->count();

        // ── Tenants near limits (>= 80 %) ───────────────────────────────────────
        $nearLimits = Organisation::withoutGlobalScopes()
            ->where('actif', true)
            ->with('plan')
            ->get()
            ->map(function (Organisation $org) {
                $usersCount    = User::withoutGlobalScope(TenantScope::class)
                    ->where('organisation_id', $org->id)->where('actif', true)->count();
                $productsCount = Product::withoutGlobalScope(TenantScope::class)
                    ->where('organisation_id', $org->id)->where('actif', true)->count();

                $nearUsers = $org->plan && $org->plan->max_utilisateurs > 0
                    && $usersCount >= $org->plan->max_utilisateurs * 0.8;
                $nearProducts = $org->plan && $org->plan->max_produits > 0
                    && $productsCount >= $org->plan->max_produits * 0.8;

                return [
                    'id'             => $org->id,
                    'nom'            => $org->nom,
                    'plan'           => $org->plan?->nom,
                    'users_count'    => $usersCount,
                    'max_users'      => $org->plan?->max_utilisateurs,
                    'products_count' => $productsCount,
                    'max_products'   => $org->plan?->max_produits,
                    'near_users'     => $nearUsers,
                    'near_products'  => $nearProducts,
                ];
            })
            ->filter(fn($o) => $o['near_users'] || $o['near_products'])
            ->values();

        // ── Most active tenants (last 30 days) ───────────────────────────────────
        $mostActive = StockMovement::withoutGlobalScope(TenantScope::class)
            ->where('date_mouvement', '>=', now()->subDays(30))
            ->selectRaw('organisation_id, COUNT(*) as total_mouvements')
            ->groupBy('organisation_id')
            ->orderByDesc('total_mouvements')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $org = Organisation::withoutGlobalScopes()->find($row->organisation_id);
                return [
                    'id'               => $row->organisation_id,
                    'nom'              => $org?->nom ?? 'Inconnu',
                    'secteur'          => $org?->secteur,
                    'total_mouvements' => (int) $row->total_mouvements,
                ];
            });

        // ── Recent signups ───────────────────────────────────────────────────────
        $recentOrgs = Organisation::withoutGlobalScopes()
            ->with('plan:id,nom')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'nom', 'secteur', 'actif', 'created_at', 'plan_id']);

        // ── System health ────────────────────────────────────────────────────────
        $dbOk    = true;
        $cacheOk = true;

        try {
            DB::select('SELECT 1' . ($isOracle ? ' FROM DUAL' : ''));
        } catch (\Throwable) {
            $dbOk = false;
        }

        try {
            Cache::put('sa_health', 1, 5);
            Cache::forget('sa_health');
        } catch (\Throwable) {
            $cacheOk = false;
        }

        return response()->json([
            'kpis'              => [
                'orgs_actives'          => $orgsActives,
                'orgs_inactives'        => $orgsInactives,
                'churn_rate'            => $churnRate,
                'nouveaux_orgs_mois'    => $newOrgsMois,
                'nouveaux_orgs_mois_precedent' => $newOrgsMoisPrecedent,
                'mrr'                   => $mrr,
                'arr'                   => $arr,
                'total_users'           => $totalUsers,
                'total_produits'        => $totalProduits,
                'total_mouvements'      => $totalMouvements,
            ],
            'plan_distribution' => $planDistribution,
            'near_limits'       => $nearLimits,
            'most_active'       => $mostActive,
            'recent_orgs'       => $recentOrgs,
            'health' => [
                'database' => $dbOk,
                'cache'    => $cacheOk,
                'api'      => true,
            ],
        ]);
    }

    public function users(): JsonResponse
    {
        $orgs = Organisation::withoutGlobalScopes()
            ->with(['users' => function ($q) {
                $q->withoutGlobalScopes()
                  ->whereNull('users.deleted_at')
                  ->whereIn('role', ['admin', 'gestionnaire', 'operateur'])
                  ->orderBy('nom')
                  ->select(['id', 'organisation_id', 'nom', 'prenom', 'email', 'role', 'actif', 'created_at']);
            }])
            ->orderBy('nom')
            ->get(['id', 'nom', 'actif']);

        return response()->json($orgs);
    }

    public function organisations(): JsonResponse
    {
        $orgs = Organisation::withoutGlobalScopes()
            ->with('plan:id,nom,prix_mensuel,ia_activee,max_utilisateurs,max_produits')
            ->orderByDesc('created_at')
            ->get();

        $isOracle = DB::connection()->getDriverName() === 'oracle';

        $result = $orgs->map(function (Organisation $org) use ($isOracle) {
            $usersCount    = User::withoutGlobalScope(TenantScope::class)
                ->where('organisation_id', $org->id)->where('actif', true)->count();
            $productsCount = Product::withoutGlobalScope(TenantScope::class)
                ->where('organisation_id', $org->id)->where('actif', true)->count();
            $mouvements    = StockMovement::withoutGlobalScope(TenantScope::class)
                ->where('organisation_id', $org->id)->count();

            // CA du mois courant = sorties × prix_vente_ht
            $caMois = StockMovement::withoutGlobalScope(TenantScope::class)
                ->where('stock_movements.organisation_id', $org->id)
                ->where('stock_movements.type_mouvement', 'sortie')
                ->join('products', 'stock_movements.product_id', '=', 'products.id')
                ->whereMonth('stock_movements.date_mouvement', now()->month)
                ->whereYear('stock_movements.date_mouvement', now()->year)
                ->selectRaw('SUM(stock_movements.quantite * products.prix_vente_ht) as total')
                ->value('total') ?? 0;

            // CA total (toutes périodes)
            $caTotal = StockMovement::withoutGlobalScope(TenantScope::class)
                ->where('stock_movements.organisation_id', $org->id)
                ->where('stock_movements.type_mouvement', 'sortie')
                ->join('products', 'stock_movements.product_id', '=', 'products.id')
                ->selectRaw('SUM(stock_movements.quantite * products.prix_vente_ht) as total')
                ->value('total') ?? 0;

            return [
                'id'             => $org->id,
                'nom'            => $org->nom,
                'secteur'        => $org->secteur,
                'email_contact'  => $org->email_contact,
                'telephone'      => $org->telephone,
                'actif'          => $org->actif,
                'onboarding_complete' => $org->onboarding_complete,
                'created_at'     => $org->created_at,
                'plan'           => $org->plan,
                'users_count'    => $usersCount,
                'products_count' => $productsCount,
                'mouvements'     => $mouvements,
                'ca_mois'        => round((float) $caMois, 3),
                'ca_total'       => round((float) $caTotal, 3),
            ];
        });

        return response()->json($result);
    }

    public function plans(): JsonResponse
    {
        $plans = Plan::where('actif', true)
            ->orderBy('prix_mensuel')
            ->get(['id', 'nom', 'prix_mensuel', 'ia_activee', 'max_utilisateurs', 'max_produits']);

        return response()->json($plans);
    }

    public function updateOrganisation(Request $request, int $id): JsonResponse
    {
        $org = Organisation::withoutGlobalScopes()->findOrFail($id);

        $data = $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $org->plan_id = $data['plan_id'];
        $org->save();

        return response()->json([
            'message'      => 'Plan mis à jour.',
            'organisation' => $org->fresh()->load('plan'),
        ]);
    }

    public function destroyOrganisation(int $id): JsonResponse
    {
        $currentUser = app('current_user');

        if ($currentUser && (int) $currentUser->organisation_id === $id) {
            return response()->json(['message' => 'Impossible de supprimer la société du super-administrateur.'], 403);
        }

        $org = Organisation::withoutGlobalScopes()->findOrFail($id);

        try {
            DB::transaction(function () use ($id) {
                DB::table('activity_logs')->where('organisation_id', $id)->delete();
                DB::table('sale_items')->where('organisation_id', $id)->delete();
                DB::table('sales')->where('organisation_id', $id)->delete();
                DB::table('client_payments')->where('organisation_id', $id)->delete();
                DB::table('clients')->where('organisation_id', $id)->delete();
                DB::table('compositions')->where('organisation_id', $id)->delete();
                DB::table('stock_movements')->where('organisation_id', $id)->delete();
                // commandes_fournisseur_items cascade sur commande_id
                DB::table('commandes_fournisseur')->where('organisation_id', $id)->delete();
                DB::table('fournisseurs')->where('organisation_id', $id)->delete();
                DB::table('type_attributes')->where('organisation_id', $id)->delete();
                DB::table('products')->where('organisation_id', $id)->delete();
                DB::table('product_types')->where('organisation_id', $id)->delete();
                DB::table('categories')->where('organisation_id', $id)->delete();
                // Hard delete users (bypass SoftDeletes — org entière supprimée)
                DB::table('users')->where('organisation_id', $id)->delete();
                // Suppression org — cascade: supplements, tables_restaurant, orders, order_items
                Organisation::withoutGlobalScopes()->where('id', $id)->delete();
            });

            return response()->json(['message' => 'Société supprimée.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::withoutGlobalScope(TenantScope::class)->findOrFail($id);

        if ($user->role === 'super_admin') {
            return response()->json(['message' => 'Impossible de modifier un super administrateur.'], 403);
        }

        $data = $request->validate([
            'prenom'   => 'sometimes|required|string|max:100',
            'nom'      => 'sometimes|required|string|max:100',
            'email'    => "sometimes|required|email|unique:users,email,{$id}",
            'role'     => 'sometimes|required|in:admin,gestionnaire,caissier,operateur',
            'password' => 'sometimes|nullable|string|min:8',
            'actif'    => 'sometimes|boolean',
        ]);

        if (isset($data['prenom']))    $user->prenom   = $data['prenom'];
        if (isset($data['nom']))       $user->nom      = $data['nom'];
        if (isset($data['email']))     $user->email    = $data['email'];
        if (isset($data['role']))      $user->role     = $data['role'];
        if (array_key_exists('actif', $data)) $user->actif = $data['actif'];
        if (!empty($data['password'])) $user->password = Hash::make($data['password']);

        try {
            $user->save();
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ], 500);
        }

        return response()->json($user->only(['id', 'nom', 'prenom', 'email', 'role', 'actif']));
    }

    public function destroyUser(int $id): JsonResponse
    {
        $currentUser = app('current_user');

        if ($currentUser && (int) $currentUser->id === $id) {
            return response()->json(['message' => 'Vous ne pouvez pas supprimer votre propre compte.'], 403);
        }

        $user = User::withoutGlobalScope(TenantScope::class)->findOrFail($id);

        if ($user->role === 'super_admin') {
            return response()->json(['message' => 'Impossible de supprimer un super administrateur.'], 403);
        }

        try {
            $user->actif = false;
            $user->save();
            $user->delete(); // soft delete — ne touche pas aux FK (sales, movements, logs)
            return response()->json(['message' => 'Utilisateur supprimé.']);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function createOrganisation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'org_nom'          => 'required|string|max:150',
            'org_secteur'      => 'nullable|string|max:100',
            'org_email'        => 'required|email|max:150',
            'org_telephone'    => 'nullable|string|max:30',
            'plan_id'          => 'required|exists:plans,id',
            'admin_prenom'     => 'required|string|max:100',
            'admin_nom'        => 'required|string|max:100',
            'admin_email'      => 'required|email|unique:users,email',
            'admin_password'   => 'required|string|min:8',
        ]);

        // ── 1. Create org + user (fast DB transaction, no AI call inside) ─────
        $org  = null;
        $user = null;

        DB::transaction(function () use ($data, &$org, &$user) {
            $org = Organisation::create([
                'nom'                 => $data['org_nom'],
                'secteur'             => isset($data['org_secteur']) ? strtolower(trim($data['org_secteur'])) : null,
                'email_contact'       => $data['org_email'],
                'telephone'           => $data['org_telephone'] ?? null,
                'plan_id'             => $data['plan_id'],
                'actif'               => true,
                'onboarding_complete' => false,
            ]);

            $user = User::create([
                'prenom'          => $data['admin_prenom'],
                'nom'             => $data['admin_nom'],
                'email'           => $data['admin_email'],
                'password'        => Hash::make($data['admin_password']),
                'role'            => 'admin',
                'organisation_id' => $org->id,
                'actif'           => true,
            ]);
        });

        // ── 2. Restauration base categories (always, no AI required) ─────────
        if ($org->isRestauration()) {
            $this->restaurantCategories->seedForOrganisation($org->id);
        }

        // ── 3. AI catalog seeding (outside the transaction) ───────────────────
        $seed = ['types' => 0, 'categories' => 0, 'products' => 0];

        if (!empty($data['org_secteur']) && $org->load('plan')->hasAIEnabled()) {
            // seedFromSector creates types + categories + products and marks
            // onboarding_complete itself when products were created.
            $seed = $this->catalogSeeder->seedFromSector($org->id, $data['org_secteur']);
        }

        return response()->json([
            'organisation' => $org->fresh()->load('plan'),
            'admin'        => $user->only(['id', 'prenom', 'nom', 'email', 'role']),
            'nb_produits'  => $seed['products'],
            'seed'         => $seed,
        ], 201);
    }
}