<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Fournisseur;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Sale;
use App\Models\User;

class PlanLimitService
{
    private static function planSlug(Organisation $org): string
    {
        return strtolower($org->plan->nom ?? 'starter');
    }

    public static function getLimit(string $resource, Organisation $org): int
    {
        return (int) config('plans.' . static::planSlug($org) . '.' . $resource, 0);
    }

    public static function getUsage(string $resource): int
    {
        return match ($resource) {
            'produits'     => Product::count(),
            'utilisateurs' => User::withoutGlobalScopes()
                ->where('organisation_id', app('current_organisation_id'))
                ->whereNull('deleted_at')
                ->count(),
            'fournisseurs' => Fournisseur::count(),
            'tables'       => RestaurantTable::count(),
            'categories'   => Category::count(),
            'ventes_mois'  => Sale::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            default        => 0,
        };
    }

    public static function check(string $resource, Organisation $org): bool
    {
        $limite = static::getLimit($resource, $org);
        if ($limite === -1) return true;
        if ($limite === 0)  return false;
        return static::getUsage($resource) < $limite;
    }

    public static function getAll(Organisation $org): array
    {
        $resources = ['produits', 'utilisateurs', 'ventes_mois', 'fournisseurs', 'tables', 'categories'];
        $result    = [];

        foreach ($resources as $r) {
            $usage  = static::getUsage($r);
            $limite = static::getLimit($r, $org);
            $result[$r] = [
                'usage'   => $usage,
                'limite'  => $limite,
                'percent' => ($limite > 0) ? min(100, (int) round($usage / $limite * 100)) : 0,
            ];
        }

        return $result;
    }

    public static function limitResponse(string $resource, Organisation $org): array
    {
        return [
            'success'       => false,
            'limit_reached' => true,
            'resource'      => $resource,
            'usage'         => static::getUsage($resource),
            'limit'         => static::getLimit($resource, $org),
            'plan'          => $org->plan?->nom,
            'message'       => static::limitMessage($resource, $org),
        ];
    }

    private static function limitMessage(string $resource, Organisation $org): string
    {
        $labels = [
            'produits'     => 'produits',
            'utilisateurs' => 'utilisateurs',
            'ventes_mois'  => 'ventes ce mois',
            'fournisseurs' => 'fournisseurs',
            'tables'       => 'tables',
            'categories'   => 'catégories',
        ];
        $limite = static::getLimit($resource, $org);
        $label  = $labels[$resource] ?? $resource;
        $plan   = $org->plan?->nom ?? 'Starter';

        return "Limite de {$limite} {$label} atteinte sur votre plan {$plan}. Passez au plan supérieur.";
    }
}
