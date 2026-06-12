<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class AIService
{
    private string $model;
    private int    $maxTokens;

    public function __construct()
    {
        $this->model     = config('ai.model', 'gpt-4o-mini');
        $this->maxTokens = config('ai.max_tokens', 1024);
    }

    /**
     * AI-assisted onboarding: suggest product types for a given sector.
     */
    public function suggestOnboarding(string $secteur): array
    {
        $orgId    = app()->bound('current_organisation_id') ? app('current_organisation_id') : 0;
        $cacheKey = "ai:onboarding:{$orgId}:" . md5($secteur);
        $ttl      = config('ai.cache.ttl_suggestions', 21600);

        return Cache::remember($cacheKey, $ttl, function () use ($secteur) {
            $prompt = str_replace('{{sector}}', $secteur, config('ai.prompts.onboarding'));

            $response = $this->call($prompt, [
                'secteur' => $secteur,
                'task'    => 'Propose 3 à 5 types de produits avec leurs attributs personnalisés.',
            ]);

            return $this->decode($response, 'types', []);
        });
    }

    /**
     * Generate a full starter catalog (types + categories + products) for a sector in one call.
     * Returns ['types' => [...], 'categories' => [...], 'products' => [...]].
     */
    public function suggestFullCatalog(string $secteur): array
    {
        $orgId    = app()->bound('current_organisation_id') ? app('current_organisation_id') : 0;
        $cacheKey = "ai:onboarding_full:{$orgId}:" . md5($secteur);
        $ttl      = config('ai.cache.ttl_suggestions', 21600);

        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }

        $prompt = str_replace('{{sector}}', $secteur, config('ai.prompts.onboarding_full'));

        $response = $this->callWithTokens($prompt, [
            'secteur' => $secteur,
            'task'    => 'Génère un catalogue de démarrage complet : types, catégories et produits réalistes avec prix en dinars tunisiens.',
        ], config('ai.max_tokens_catalog', 3800));

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return ['types' => [], 'categories' => [], 'products' => []];
        }

        $result = [
            'types'      => $data['types']      ?? [],
            'categories' => $data['categories'] ?? [],
            'products'   => $data['products']   ?? [],
        ];

        // Only cache a genuine AI result — never cache an empty/failed response,
        // so adding a valid API key later takes effect immediately.
        if (!empty($result['products'])) {
            Cache::put($cacheKey, $result, $ttl);
        }

        return $result;
    }

    /**
     * Suggest real products (with prices and categories) for a given sector.
     */
    public function suggestProducts(string $secteur): array
    {
        $orgId    = app()->bound('current_organisation_id') ? app('current_organisation_id') : 0;
        $cacheKey = "ai:onboarding_products:{$orgId}:" . md5($secteur);
        $ttl      = config('ai.cache.ttl_suggestions', 21600);

        return Cache::remember($cacheKey, $ttl, function () use ($secteur) {
            $prompt = str_replace('{{sector}}', $secteur, config('ai.prompts.onboarding_products'));

            $response = $this->callWithTokens($prompt, [
                'secteur' => $secteur,
                'task'    => 'Génère des produits réalistes avec prix en dinars tunisiens pour ce secteur.',
            ], config('ai.max_tokens_products', 2500));

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                return ['categories' => [], 'products' => []];
            }
            return [
                'categories' => $data['categories'] ?? [],
                'products'   => $data['products']   ?? [],
            ];
        });
    }

    /**
     * Suggest reorder quantities based on current stock levels.
     */
    public function suggestReorder(array $products): array
    {
        $orgId    = app()->bound('current_organisation_id') ? app('current_organisation_id') : 0;
        $cacheKey = "ai:reorder:{$orgId}:" . md5(serialize(array_column($products, 'id')));
        $ttl      = config('ai.cache.ttl_suggestions', 21600);

        return Cache::remember($cacheKey, $ttl, function () use ($products) {
            $response = $this->call(config('ai.prompts.reorder'), [
                'products' => $products,
                'task'     => 'Suggère des quantités de réapprovisionnement avec une confiance en pourcentage.',
            ]);

            return $this->decode($response, 'suggestions', []);
        });
    }

    /**
     * Detect stock movement anomalies using 3σ rule.
     */
    public function detectAnomaly(array $movements): array
    {
        $orgId    = app()->bound('current_organisation_id') ? app('current_organisation_id') : 0;
        $cacheKey = "ai:anomaly:{$orgId}:" . md5(serialize($movements));
        $ttl      = config('ai.cache.ttl_anomalies', 3600);

        return Cache::remember($cacheKey, $ttl, function () use ($movements) {
            $response = $this->call(config('ai.prompts.anomaly'), [
                'mouvements' => $movements,
                'task'       => 'Identifie les anomalies (règle 3σ) avec niveau de sévérité: low|medium|high.',
            ]);

            return $this->decode($response, 'anomalies', []);
        });
    }

    /**
     * Forecast 30-day demand for a product.
     */
    public function forecastDemand(array $product, array $history): array
    {
        $orgId    = app()->bound('current_organisation_id') ? app('current_organisation_id') : 0;
        $cacheKey = "ai:forecast:{$orgId}:{$product['id']}";
        $ttl      = config('ai.cache.ttl_forecasts', 86400);

        return Cache::remember($cacheKey, $ttl, function () use ($product, $history) {
            $response = $this->call(config('ai.prompts.forecast'), [
                'produit'    => $product,
                'historique' => $history,
                'task'       => 'Prévision quotidienne sur 30 jours avec intervalle de confiance.',
            ]);

            return $this->decode($response, 'forecast', []);
        });
    }

    /**
     * Compute predictive KPIs for the dashboard.
     */
    public function predictiveKpis(array $products): array
    {
        $orgId    = app()->bound('current_organisation_id') ? app('current_organisation_id') : 0;
        $cacheKey = "ai:kpis:{$orgId}";
        $ttl      = config('ai.cache.ttl_suggestions', 21600);

        return Cache::remember($cacheKey, $ttl, function () use ($products) {
            $response = $this->call(config('ai.prompts.kpis'), [
                'produits' => $products,
                'task'     => 'Calcule: taux_rotation_prevu, risque_rupture_score, valeur_optimale_stock.',
            ]);

            return $this->decode($response, 'kpis', []);
        });
    }

    // ─── Private Helpers ───────────────────────────────────────────────────────

    private function callWithTokens(string $systemPrompt, array $userContent, int $maxTokens): string
    {
        try {
            $response = OpenAI::chat()->create([
                'model'           => $this->model,
                'max_tokens'      => $maxTokens,
                'response_format' => ['type' => 'json_object'],
                'messages'        => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => json_encode($userContent, JSON_UNESCAPED_UNICODE)],
                ],
            ]);
            return $response->choices[0]->message->content ?? '{}';
        } catch (\Throwable $e) {
            Log::error('AIService callWithTokens failed', ['error' => $e->getMessage()]);
            return '{}';
        }
    }

    private function call(string $systemPrompt, array $userContent): string
    {
        try {
            $response = OpenAI::chat()->create([
                'model'       => $this->model,
                'max_tokens'  => $this->maxTokens,
                'response_format' => ['type' => 'json_object'],
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => json_encode($userContent, JSON_UNESCAPED_UNICODE)],
                ],
            ]);

            return $response->choices[0]->message->content ?? '{}';
        } catch (\Throwable $e) {
            Log::error('AIService call failed', ['error' => $e->getMessage()]);
            return '{}';
        }
    }

    private function decode(string $json, string $key, mixed $default): mixed
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            return $default;
        }

        return $data[$key] ?? $data;
    }
}
