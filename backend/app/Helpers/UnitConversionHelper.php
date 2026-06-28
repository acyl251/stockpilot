<?php

namespace App\Helpers;

class UnitConversionHelper
{
    // Base unit = grams for mass, millilitres for volume
    private const MASS_BASE = ['kg' => 1000.0, 'g' => 1.0, 'mg' => 0.001];
    private const VOL_BASE  = ['l' => 1000.0, 'dl' => 100.0, 'cl' => 10.0, 'ml' => 1.0];

    // Discrete units: any pair within a group is compatible (factor = 1)
    private const DISCRETE_GROUPS = [
        ['pièce', 'piece', 'pcs', 'unité', 'unite', 'u'],
        ['portion'],
        ['dose'],
        ['boite', 'boîte', 'bte'],
        ['paquet', 'pqt', 'pack'],
    ];

    /**
     * Returns the factor f such that:
     *   cout_ligne = prix_achat_par_unite_stock × quantite_recette × f
     *
     * Returns null when units are incompatible (e.g. kg → pièce).
     */
    public static function getConversionFactor(string $uniteStock, string $uniteRecette): ?float
    {
        $us = strtolower(trim($uniteStock));
        $ur = strtolower(trim($uniteRecette));

        if ($us === '' || $ur === '' || $us === $ur) {
            return 1.0;
        }

        // Mass
        if (isset(self::MASS_BASE[$us], self::MASS_BASE[$ur])) {
            return self::MASS_BASE[$ur] / self::MASS_BASE[$us];
        }

        // Volume
        if (isset(self::VOL_BASE[$us], self::VOL_BASE[$ur])) {
            return self::VOL_BASE[$ur] / self::VOL_BASE[$us];
        }

        // Discrete groups
        foreach (self::DISCRETE_GROUPS as $group) {
            if (in_array($us, $group, true) && in_array($ur, $group, true)) {
                return 1.0;
            }
        }

        return null; // incompatible
    }
}
