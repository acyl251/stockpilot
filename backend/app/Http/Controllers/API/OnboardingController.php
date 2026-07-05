<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Composition;
use App\Models\Fournisseur;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\Sale;
use App\Models\TypeAttribute;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    public function __construct(private AIService $aiService) {}

    /** Checklist de démarrage — admin uniquement */
    public function checklist(): JsonResponse
    {
        $user = app('current_user');
        if (! in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['message' => 'Accès réservé aux administrateurs.'], 403);
        }

        $org           = $user->organisation;
        $isRestauration = $org->secteur === Organisation::SECTEUR_RESTAURATION;

        $productCount     = Product::count();
        $composeCount     = Product::where('type', Product::TYPE_COMPOSE)->count();
        $compositionCount = Composition::count();
        $saleCount        = Sale::count();
        $fournisseurCount = Fournisseur::count();
        $hasCollaborateur = User::where('organisation_id', app('current_organisation_id'))
            ->where('id', '!=', $user->id)
            ->whereNull('deleted_at')
            ->exists();

        $steps = [
            [
                'id'        => 'configuration',
                'label'     => 'Configurez votre restaurant',
                'completed' => ! empty($org->nom)
                    && ! empty($org->secteur)
                    && ! empty($org->telephone)
                    && ! empty($org->adresse),
                'link'      => '/app/configuration',
                'visible'   => true,
            ],
            [
                'id'        => 'produits',
                'label'     => 'Ajoutez vos premiers produits',
                'completed' => $productCount > 0,
                'link'      => '/app/products',
                'visible'   => true,
            ],
            [
                'id'        => 'menu',
                'label'     => 'Créez votre menu',
                'completed' => $composeCount > 0,
                'link'      => '/app/menu',
                'visible'   => $isRestauration,
            ],
            [
                'id'        => 'recettes',
                'label'     => 'Configurez vos recettes',
                'completed' => $compositionCount > 0,
                'link'      => '/app/menu',
                'visible'   => $isRestauration,
            ],
            [
                'id'        => 'vente',
                'label'     => 'Faites votre première vente',
                'completed' => $saleCount > 0,
                'link'      => '/app/caisse',
                'visible'   => true,
            ],
            [
                'id'        => 'fournisseur',
                'label'     => 'Ajoutez un fournisseur',
                'completed' => $fournisseurCount > 0,
                'link'      => '/app/fournisseurs',
                'visible'   => true,
            ],
            [
                'id'        => 'collaborateur',
                'label'     => 'Invitez un collaborateur',
                'completed' => $hasCollaborateur,
                'link'      => '/app/utilisateurs',
                'visible'   => true,
            ],
        ];

        $visibleSteps   = array_values(array_filter($steps, fn($s) => $s['visible']));
        $completedCount = count(array_filter($visibleSteps, fn($s) => $s['completed']));
        $totalCount     = count($visibleSteps);

        return response()->json([
            'steps'           => $visibleSteps,
            'completed_count' => $completedCount,
            'total_count'     => $totalCount,
            'all_completed'   => $completedCount === $totalCount,
        ]);
    }

    /** Étape 2 — suggestions de types de produits */
    public function suggest(Request $request): JsonResponse
    {
        $request->validate(['secteur' => 'required|string|max:100']);

        $org = app('current_user')->organisation;

        if (! $org->hasAIEnabled()) {
            return $this->errorResponse("Les fonctionnalités d'IA ne sont pas incluses dans votre plan actuel.", 403);
        }

        $suggestions = $this->aiService->suggestOnboarding($request->secteur);

        return response()->json(['suggestions' => $suggestions]);
    }

    /** Étape 3 — suggestions de produits réels par secteur */
    public function suggestProducts(Request $request): JsonResponse
    {
        $request->validate(['secteur' => 'required|string|max:100']);

        $org = app('current_user')->organisation;

        if (! $org->hasAIEnabled()) {
            return $this->errorResponse("Les fonctionnalités d'IA ne sont pas incluses dans votre plan actuel.", 403);
        }

        $result = $this->aiService->suggestProducts($request->secteur);

        return response()->json($result);
    }

    /** Confirmation finale — crée types + catégories + produits sélectionnés */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'types'                           => 'nullable|array',
            'types.*.nom'                     => 'required|string|max:150',
            'types.*.icone'                   => 'nullable|string|max:50',
            'types.*.attributs'               => 'nullable|array',
            'types.*.attributs.*.nom'         => 'required|string|max:100',
            'types.*.attributs.*.label'       => 'required|string|max:150',
            'types.*.attributs.*.type_donnee' => 'required|in:text,number,date,boolean,select',
            'products'                        => 'nullable|array',
            'products.*.nom'                  => 'required|string|max:200',
            'products.*.reference'            => 'nullable|string|max:100',
            'products.*.description'          => 'nullable|string',
            'products.*.categorie'            => 'nullable|string|max:150',
            'products.*.unite_mesure'         => 'nullable|string|max:30',
            'products.*.quantite'             => 'nullable|numeric|min:0',
            'products.*.seuil_alerte'         => 'nullable|numeric|min:0',
            'products.*.prix_achat_ht'        => 'nullable|numeric|min:0',
            'products.*.prix_vente_ht'        => 'nullable|numeric|min:0',
            'products.*.taux_tva'             => 'nullable|integer|in:0,7,19',
        ]);

        return DB::transaction(function () use ($request) {
            $orgId = app('current_organisation_id');

            // ── 1. Créer les types de produits ────────────────────────────────
            foreach ($request->types ?? [] as $typeData) {
                $type = ProductType::create([
                    'nom'            => $typeData['nom'],
                    'icone'          => $typeData['icone'] ?? null,
                    'suggere_par_ia' => true,
                ]);

                foreach ($typeData['attributs'] ?? [] as $i => $attr) {
                    TypeAttribute::create(array_merge($attr, [
                        'product_type_id' => $type->id,
                        'ordre'           => $attr['ordre'] ?? $i,
                    ]));
                }
            }

            // ── 2. Créer les catégories et produits ───────────────────────────
            $categoryCache = []; // nom → id

            foreach ($request->products ?? [] as $productData) {
                // Résoudre/créer la catégorie
                $catNom = $productData['categorie'] ?? 'Général';
                if (!isset($categoryCache[$catNom])) {
                    $cat = Category::firstOrCreate(
                        ['nom' => $catNom, 'organisation_id' => $orgId],
                        ['couleur' => $this->randomColor(), 'actif' => true]
                    );
                    $categoryCache[$catNom] = $cat->id;
                }

                // Générer une référence si absente ou en conflit
                $reference = $productData['reference'] ?? Str::upper(Str::substr(Str::slug($productData['nom']), 0, 5)) . '-' . rand(100, 999);

                // Éviter les doublons de référence
                if (Product::where('reference', $reference)->exists()) {
                    $reference = $reference . '-' . rand(10, 99);
                }

                Product::create([
                    'nom'           => $productData['nom'],
                    'reference'     => $reference,
                    'description'   => $productData['description'] ?? null,
                    'category_id'   => $categoryCache[$catNom],
                    'unite_mesure'  => $productData['unite_mesure'] ?? 'pcs',
                    'quantite'      => $productData['quantite'] ?? 0,
                    'seuil_alerte'  => $productData['seuil_alerte'] ?? 5,
                    'prix_achat_ht' => $productData['prix_achat_ht'] ?? 0,
                    'prix_vente_ht' => $productData['prix_vente_ht'] ?? 0,
                    'taux_tva'      => $productData['taux_tva'] ?? 19,
                    'actif'         => true,
                ]);
            }

            // ── 3. Marquer l'onboarding comme terminé ─────────────────────────
            Organisation::withoutGlobalScopes()
                ->where('id', $orgId)
                ->update(['onboarding_complete' => true]);

            $nbProduits = count($request->products ?? []);

            return response()->json([
                'message'     => 'Onboarding complété avec succès.',
                'nb_produits' => $nbProduits,
            ]);
        });
    }

    private function randomColor(): string
    {
        $colors = ['#3b82f6','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4','#84cc16','#f97316'];
        return $colors[array_rand($colors)];
    }
}
