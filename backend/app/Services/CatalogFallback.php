<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Offline starter catalogs per sector.
 *
 * Used by CatalogSeederService as graceful degradation when the AI service is
 * unavailable (missing/invalid OPENAI_API_KEY or API down). When a valid key is
 * configured, the real AI output takes precedence and this is never used.
 *
 * Each catalog mirrors AIService::suggestFullCatalog():
 *   ['types' => [...], 'categories' => [...], 'products' => [...]]
 */
class CatalogFallback
{
    /** Resolve the best matching catalog for a free-text sector. */
    public static function forSector(string $secteur): array
    {
        $s = Str::lower(Str::ascii($secteur));

        $match = function (array $needles) use ($s): bool {
            foreach ($needles as $n) {
                if (str_contains($s, $n)) return true;
            }
            return false;
        };

        return match (true) {
            $match(['pharma', 'parapharma', 'medic', 'sante'])              => self::pharmacie(),
            $match(['electro', 'informa', 'high-tech', 'high tech', 'gsm']) => self::electronique(),
            $match(['menuis', 'ebenist', 'bois', 'charpent', 'meuble'])     => self::menuiserie(),
            $match(['quincaill', 'btp', 'bricol', 'construct', 'batiment']) => self::quincaillerie(),
            $match(['cosmet', 'beaute', 'parfum', 'maquill'])               => self::cosmetique(),
            $match(['boulang', 'patiss', 'viennois'])                       => self::boulangerie(),
            $match(['restau', 'fast', 'food', 'cafe', 'snack'])             => self::restauration(),
            $match(['aliment', 'epicer', 'superette', 'grocer'])            => self::alimentaire(),
            default                                                          => self::general(),
        };
    }

    // ── Pharmacie ────────────────────────────────────────────────────────────

    private static function pharmacie(): array
    {
        return [
            'types' => [
                [
                    'nom' => 'Médicament', 'icone' => '💊',
                    'description' => 'Produit pharmaceutique avec lot et péremption',
                    'attributs' => [
                        ['nom' => 'numero_lot',      'label' => 'N° de lot',           'type_donnee' => 'text',    'obligatoire' => true],
                        ['nom' => 'date_peremption', 'label' => 'Date de péremption',   'type_donnee' => 'date',    'obligatoire' => true],
                        ['nom' => 'ordonnance',      'label' => 'Requiert ordonnance',  'type_donnee' => 'boolean', 'obligatoire' => false],
                    ],
                ],
                [
                    'nom' => 'Parapharmacie', 'icone' => '🧴',
                    'description' => 'Produit de soin et d\'hygiène sans ordonnance',
                    'attributs' => [
                        ['nom' => 'marque',          'label' => 'Marque',              'type_donnee' => 'text', 'obligatoire' => false],
                        ['nom' => 'contenance_ml',   'label' => 'Contenance (ml)',     'type_donnee' => 'number', 'obligatoire' => false],
                    ],
                ],
                [
                    'nom' => 'Matériel médical', 'icone' => '🩺',
                    'description' => 'Dispositif et consommable médical',
                    'attributs' => [
                        ['nom' => 'sterile',      'label' => 'Stérile',      'type_donnee' => 'boolean', 'obligatoire' => true],
                        ['nom' => 'usage_unique', 'label' => 'Usage unique', 'type_donnee' => 'boolean', 'obligatoire' => false],
                    ],
                ],
            ],
            'categories' => ['Antalgiques', 'Antibiotiques', 'Parapharmacie', 'Matériel médical', 'Vitamines & compléments'],
            'products' => [
                ['nom' => 'Paracétamol 500mg (boîte 20)', 'reference' => 'MED-001', 'description' => 'Antalgique et antipyrétique', 'categorie' => 'Antalgiques', 'unite_mesure' => 'boite', 'quantite' => 120, 'seuil_alerte' => 20, 'prix_achat_ht' => 1.800, 'prix_vente_ht' => 3.200, 'taux_tva' => 7],
                ['nom' => 'Ibuprofène 400mg (boîte 30)', 'reference' => 'MED-002', 'description' => 'Anti-inflammatoire non stéroïdien', 'categorie' => 'Antalgiques', 'unite_mesure' => 'boite', 'quantite' => 80, 'seuil_alerte' => 15, 'prix_achat_ht' => 2.500, 'prix_vente_ht' => 4.500, 'taux_tva' => 7],
                ['nom' => 'Aspirine 500mg (boîte 20)', 'reference' => 'MED-003', 'description' => 'Antalgique et anti-inflammatoire', 'categorie' => 'Antalgiques', 'unite_mesure' => 'boite', 'quantite' => 60, 'seuil_alerte' => 12, 'prix_achat_ht' => 1.500, 'prix_vente_ht' => 2.800, 'taux_tva' => 7],
                ['nom' => 'Amoxicilline 1g (boîte 12)', 'reference' => 'MED-010', 'description' => 'Antibiotique à large spectre', 'categorie' => 'Antibiotiques', 'unite_mesure' => 'boite', 'quantite' => 45, 'seuil_alerte' => 10, 'prix_achat_ht' => 4.200, 'prix_vente_ht' => 7.100, 'taux_tva' => 7],
                ['nom' => 'Augmentin 1g (boîte 16)', 'reference' => 'MED-011', 'description' => 'Amoxicilline + acide clavulanique', 'categorie' => 'Antibiotiques', 'unite_mesure' => 'boite', 'quantite' => 30, 'seuil_alerte' => 8, 'prix_achat_ht' => 6.800, 'prix_vente_ht' => 11.500, 'taux_tva' => 7],
                ['nom' => 'Sérum physiologique (boîte 20 dosettes)', 'reference' => 'PARA-020', 'description' => 'Nettoyage nasal et oculaire', 'categorie' => 'Parapharmacie', 'unite_mesure' => 'boite', 'quantite' => 100, 'seuil_alerte' => 20, 'prix_achat_ht' => 2.000, 'prix_vente_ht' => 3.900, 'taux_tva' => 7],
                ['nom' => 'Crème hydratante visage 50ml', 'reference' => 'PARA-021', 'description' => 'Soin hydratant quotidien', 'categorie' => 'Parapharmacie', 'unite_mesure' => 'pcs', 'quantite' => 40, 'seuil_alerte' => 10, 'prix_achat_ht' => 9.000, 'prix_vente_ht' => 16.500, 'taux_tva' => 19],
                ['nom' => 'Gel hydroalcoolique 500ml', 'reference' => 'PARA-022', 'description' => 'Désinfectant pour les mains', 'categorie' => 'Parapharmacie', 'unite_mesure' => 'pcs', 'quantite' => 70, 'seuil_alerte' => 15, 'prix_achat_ht' => 3.500, 'prix_vente_ht' => 6.500, 'taux_tva' => 19],
                ['nom' => 'Masque chirurgical (boîte 50)', 'reference' => 'MAT-030', 'description' => 'Masque de protection 3 plis', 'categorie' => 'Matériel médical', 'unite_mesure' => 'boite', 'quantite' => 90, 'seuil_alerte' => 20, 'prix_achat_ht' => 4.000, 'prix_vente_ht' => 7.500, 'taux_tva' => 7],
                ['nom' => 'Thermomètre digital', 'reference' => 'MAT-031', 'description' => 'Mesure rapide de la température', 'categorie' => 'Matériel médical', 'unite_mesure' => 'pcs', 'quantite' => 25, 'seuil_alerte' => 6, 'prix_achat_ht' => 7.000, 'prix_vente_ht' => 13.000, 'taux_tva' => 19],
                ['nom' => 'Vitamine C 1000mg (boîte 20)', 'reference' => 'VIT-040', 'description' => 'Complément énergie et immunité', 'categorie' => 'Vitamines & compléments', 'unite_mesure' => 'boite', 'quantite' => 55, 'seuil_alerte' => 12, 'prix_achat_ht' => 5.500, 'prix_vente_ht' => 9.900, 'taux_tva' => 7],
                ['nom' => 'Magnésium B6 (boîte 30)', 'reference' => 'VIT-041', 'description' => 'Réduction de la fatigue', 'categorie' => 'Vitamines & compléments', 'unite_mesure' => 'boite', 'quantite' => 35, 'seuil_alerte' => 8, 'prix_achat_ht' => 6.200, 'prix_vente_ht' => 11.000, 'taux_tva' => 7],
            ],
        ];
    }

    // ── Boulangerie / Pâtisserie ─────────────────────────────────────────────

    private static function boulangerie(): array
    {
        return [
            'types' => [
                [
                    'nom' => 'Produit de boulangerie', 'icone' => '🥖',
                    'description' => 'Pain et viennoiserie à DLC courte',
                    'attributs' => [
                        ['nom' => 'date_fabrication', 'label' => 'Date de fabrication', 'type_donnee' => 'date',    'obligatoire' => true],
                        ['nom' => 'sans_gluten',      'label' => 'Sans gluten',         'type_donnee' => 'boolean', 'obligatoire' => false],
                    ],
                ],
                [
                    'nom' => 'Matière première', 'icone' => '🌾',
                    'description' => 'Ingrédient de production',
                    'attributs' => [
                        ['nom' => 'fournisseur',     'label' => 'Fournisseur',       'type_donnee' => 'text', 'obligatoire' => false],
                        ['nom' => 'date_peremption', 'label' => 'Date de péremption', 'type_donnee' => 'date', 'obligatoire' => false],
                    ],
                ],
            ],
            'categories' => ['Pains', 'Viennoiseries', 'Pâtisseries', 'Matières premières'],
            'products' => [
                ['nom' => 'Baguette tradition', 'reference' => 'PAIN-001', 'description' => 'Baguette artisanale', 'categorie' => 'Pains', 'unite_mesure' => 'pcs', 'quantite' => 150, 'seuil_alerte' => 30, 'prix_achat_ht' => 0.200, 'prix_vente_ht' => 0.250, 'taux_tva' => 0],
                ['nom' => 'Pain complet', 'reference' => 'PAIN-002', 'description' => 'Pain à la farine complète', 'categorie' => 'Pains', 'unite_mesure' => 'pcs', 'quantite' => 60, 'seuil_alerte' => 15, 'prix_achat_ht' => 0.600, 'prix_vente_ht' => 0.900, 'taux_tva' => 0],
                ['nom' => 'Croissant au beurre', 'reference' => 'VIEN-010', 'description' => 'Viennoiserie pur beurre', 'categorie' => 'Viennoiseries', 'unite_mesure' => 'pcs', 'quantite' => 80, 'seuil_alerte' => 20, 'prix_achat_ht' => 0.450, 'prix_vente_ht' => 0.800, 'taux_tva' => 7],
                ['nom' => 'Pain au chocolat', 'reference' => 'VIEN-011', 'description' => 'Chocolatine feuilletée', 'categorie' => 'Viennoiseries', 'unite_mesure' => 'pcs', 'quantite' => 75, 'seuil_alerte' => 20, 'prix_achat_ht' => 0.500, 'prix_vente_ht' => 0.900, 'taux_tva' => 7],
                ['nom' => 'Millefeuille', 'reference' => 'PAT-020', 'description' => 'Pâtisserie crème vanille', 'categorie' => 'Pâtisseries', 'unite_mesure' => 'pcs', 'quantite' => 30, 'seuil_alerte' => 8, 'prix_achat_ht' => 1.200, 'prix_vente_ht' => 2.500, 'taux_tva' => 7],
                ['nom' => 'Éclair au chocolat', 'reference' => 'PAT-021', 'description' => 'Pâte à choux garnie', 'categorie' => 'Pâtisseries', 'unite_mesure' => 'pcs', 'quantite' => 40, 'seuil_alerte' => 10, 'prix_achat_ht' => 1.000, 'prix_vente_ht' => 2.200, 'taux_tva' => 7],
                ['nom' => 'Farine de blé T55 (sac 25kg)', 'reference' => 'MP-030', 'description' => 'Farine panifiable', 'categorie' => 'Matières premières', 'unite_mesure' => 'sac', 'quantite' => 20, 'seuil_alerte' => 5, 'prix_achat_ht' => 35.000, 'prix_vente_ht' => 35.000, 'taux_tva' => 7],
                ['nom' => 'Beurre pâtissier (plaque 1kg)', 'reference' => 'MP-031', 'description' => 'Beurre de tourage', 'categorie' => 'Matières premières', 'unite_mesure' => 'kg', 'quantite' => 30, 'seuil_alerte' => 8, 'prix_achat_ht' => 12.000, 'prix_vente_ht' => 12.000, 'taux_tva' => 7],
                ['nom' => 'Levure boulangère (kg)', 'reference' => 'MP-032', 'description' => 'Levure fraîche', 'categorie' => 'Matières premières', 'unite_mesure' => 'kg', 'quantite' => 15, 'seuil_alerte' => 4, 'prix_achat_ht' => 6.000, 'prix_vente_ht' => 6.000, 'taux_tva' => 7],
                ['nom' => 'Sucre cristallisé (sac 50kg)', 'reference' => 'MP-033', 'description' => 'Sucre blanc', 'categorie' => 'Matières premières', 'unite_mesure' => 'sac', 'quantite' => 12, 'seuil_alerte' => 3, 'prix_achat_ht' => 90.000, 'prix_vente_ht' => 90.000, 'taux_tva' => 7],
            ],
        ];
    }

    // ── Restauration ─────────────────────────────────────────────────────────

    private static function restauration(): array
    {
        return [
            'types' => [
                [
                    'nom' => 'Plat / Menu', 'icone' => '🍽️',
                    'description' => 'Article vendu au client',
                    'attributs' => [
                        ['nom' => 'vegetarien', 'label' => 'Végétarien', 'type_donnee' => 'boolean', 'obligatoire' => false],
                        ['nom' => 'epice',      'label' => 'Épicé',      'type_donnee' => 'boolean', 'obligatoire' => false],
                    ],
                ],
                [
                    'nom' => 'Ingrédient', 'icone' => '🥕',
                    'description' => 'Matière première de cuisine',
                    'attributs' => [
                        ['nom' => 'date_peremption', 'label' => 'Date de péremption', 'type_donnee' => 'date',   'obligatoire' => false],
                        ['nom' => 'conservation',    'label' => 'Conservation',       'type_donnee' => 'select', 'obligatoire' => false, 'options_select' => 'Ambiant,Réfrigéré,Congelé'],
                    ],
                ],
            ],
            'categories' => ['Boissons', 'Viandes & poissons', 'Légumes & fruits', 'Épicerie', 'Produits laitiers'],
            'products' => [
                ['nom' => 'Eau minérale 1.5L', 'reference' => 'BOIS-001', 'description' => 'Bouteille d\'eau', 'categorie' => 'Boissons', 'unite_mesure' => 'pcs', 'quantite' => 200, 'seuil_alerte' => 40, 'prix_achat_ht' => 0.500, 'prix_vente_ht' => 1.500, 'taux_tva' => 7],
                ['nom' => 'Soda canette 33cl', 'reference' => 'BOIS-002', 'description' => 'Boisson gazeuse', 'categorie' => 'Boissons', 'unite_mesure' => 'pcs', 'quantite' => 150, 'seuil_alerte' => 30, 'prix_achat_ht' => 0.900, 'prix_vente_ht' => 2.500, 'taux_tva' => 19],
                ['nom' => 'Escalope de poulet (kg)', 'reference' => 'VIA-010', 'description' => 'Filet de poulet frais', 'categorie' => 'Viandes & poissons', 'unite_mesure' => 'kg', 'quantite' => 40, 'seuil_alerte' => 10, 'prix_achat_ht' => 11.000, 'prix_vente_ht' => 11.000, 'taux_tva' => 7],
                ['nom' => 'Viande hachée (kg)', 'reference' => 'VIA-011', 'description' => 'Bœuf haché', 'categorie' => 'Viandes & poissons', 'unite_mesure' => 'kg', 'quantite' => 25, 'seuil_alerte' => 6, 'prix_achat_ht' => 24.000, 'prix_vente_ht' => 24.000, 'taux_tva' => 7],
                ['nom' => 'Tomates fraîches (kg)', 'reference' => 'LEG-020', 'description' => 'Tomates de saison', 'categorie' => 'Légumes & fruits', 'unite_mesure' => 'kg', 'quantite' => 60, 'seuil_alerte' => 15, 'prix_achat_ht' => 1.800, 'prix_vente_ht' => 1.800, 'taux_tva' => 0],
                ['nom' => 'Pommes de terre (sac 10kg)', 'reference' => 'LEG-021', 'description' => 'Pommes de terre', 'categorie' => 'Légumes & fruits', 'unite_mesure' => 'sac', 'quantite' => 20, 'seuil_alerte' => 5, 'prix_achat_ht' => 12.000, 'prix_vente_ht' => 12.000, 'taux_tva' => 0],
                ['nom' => 'Huile d\'olive 5L', 'reference' => 'EPI-030', 'description' => 'Huile d\'olive extra vierge', 'categorie' => 'Épicerie', 'unite_mesure' => 'pcs', 'quantite' => 30, 'seuil_alerte' => 8, 'prix_achat_ht' => 38.000, 'prix_vente_ht' => 38.000, 'taux_tva' => 7],
                ['nom' => 'Pâtes (carton 5kg)', 'reference' => 'EPI-031', 'description' => 'Pâtes alimentaires', 'categorie' => 'Épicerie', 'unite_mesure' => 'carton', 'quantite' => 25, 'seuil_alerte' => 6, 'prix_achat_ht' => 9.000, 'prix_vente_ht' => 9.000, 'taux_tva' => 7],
                ['nom' => 'Fromage râpé (kg)', 'reference' => 'LAIT-040', 'description' => 'Mozzarella râpée', 'categorie' => 'Produits laitiers', 'unite_mesure' => 'kg', 'quantite' => 18, 'seuil_alerte' => 5, 'prix_achat_ht' => 16.000, 'prix_vente_ht' => 16.000, 'taux_tva' => 7],
                ['nom' => 'Beurre (plaque 500g)', 'reference' => 'LAIT-041', 'description' => 'Beurre de cuisine', 'categorie' => 'Produits laitiers', 'unite_mesure' => 'pcs', 'quantite' => 40, 'seuil_alerte' => 10, 'prix_achat_ht' => 5.500, 'prix_vente_ht' => 5.500, 'taux_tva' => 7],
            ],
        ];
    }

    // ── Alimentaire / Épicerie ───────────────────────────────────────────────

    private static function alimentaire(): array
    {
        return [
            'types' => [
                [
                    'nom' => 'Produit alimentaire', 'icone' => '🛒',
                    'description' => 'Denrée avec traçabilité DLC',
                    'attributs' => [
                        ['nom' => 'date_peremption', 'label' => 'Date de péremption',  'type_donnee' => 'date',   'obligatoire' => true],
                        ['nom' => 'conservation',    'label' => 'Conservation',        'type_donnee' => 'select', 'obligatoire' => false, 'options_select' => 'Ambiant,Réfrigéré,Congelé'],
                    ],
                ],
                [
                    'nom' => 'Boisson', 'icone' => '🥤',
                    'description' => 'Boisson et liquide',
                    'attributs' => [
                        ['nom' => 'volume_ml', 'label' => 'Volume (ml)', 'type_donnee' => 'number',  'obligatoire' => false],
                        ['nom' => 'gazeux',    'label' => 'Gazeux',      'type_donnee' => 'boolean', 'obligatoire' => false],
                    ],
                ],
            ],
            'categories' => ['Épicerie sèche', 'Boissons', 'Produits frais', 'Hygiène & entretien', 'Conserves'],
            'products' => [
                ['nom' => 'Sucre 1kg', 'reference' => 'EPI-001', 'description' => 'Sucre cristallisé', 'categorie' => 'Épicerie sèche', 'unite_mesure' => 'pcs', 'quantite' => 120, 'seuil_alerte' => 25, 'prix_achat_ht' => 1.300, 'prix_vente_ht' => 1.600, 'taux_tva' => 7],
                ['nom' => 'Farine 1kg', 'reference' => 'EPI-002', 'description' => 'Farine pâtissière', 'categorie' => 'Épicerie sèche', 'unite_mesure' => 'pcs', 'quantite' => 100, 'seuil_alerte' => 20, 'prix_achat_ht' => 1.100, 'prix_vente_ht' => 1.400, 'taux_tva' => 7],
                ['nom' => 'Café moulu 250g', 'reference' => 'EPI-003', 'description' => 'Café torréfié', 'categorie' => 'Épicerie sèche', 'unite_mesure' => 'pcs', 'quantite' => 60, 'seuil_alerte' => 15, 'prix_achat_ht' => 3.800, 'prix_vente_ht' => 5.500, 'taux_tva' => 7],
                ['nom' => 'Eau minérale 1.5L', 'reference' => 'BOIS-010', 'description' => 'Bouteille d\'eau', 'categorie' => 'Boissons', 'unite_mesure' => 'pcs', 'quantite' => 200, 'seuil_alerte' => 40, 'prix_achat_ht' => 0.500, 'prix_vente_ht' => 0.850, 'taux_tva' => 7],
                ['nom' => 'Jus d\'orange 1L', 'reference' => 'BOIS-011', 'description' => 'Jus de fruits', 'categorie' => 'Boissons', 'unite_mesure' => 'pcs', 'quantite' => 70, 'seuil_alerte' => 15, 'prix_achat_ht' => 1.600, 'prix_vente_ht' => 2.500, 'taux_tva' => 19],
                ['nom' => 'Lait demi-écrémé 1L', 'reference' => 'FRAIS-020', 'description' => 'Lait UHT', 'categorie' => 'Produits frais', 'unite_mesure' => 'pcs', 'quantite' => 90, 'seuil_alerte' => 20, 'prix_achat_ht' => 1.000, 'prix_vente_ht' => 1.350, 'taux_tva' => 7],
                ['nom' => 'Yaourt nature (pack 8)', 'reference' => 'FRAIS-021', 'description' => 'Yaourts brassés', 'categorie' => 'Produits frais', 'unite_mesure' => 'pack', 'quantite' => 50, 'seuil_alerte' => 12, 'prix_achat_ht' => 2.200, 'prix_vente_ht' => 3.200, 'taux_tva' => 7],
                ['nom' => 'Savon de Marseille', 'reference' => 'HYG-030', 'description' => 'Savon solide', 'categorie' => 'Hygiène & entretien', 'unite_mesure' => 'pcs', 'quantite' => 80, 'seuil_alerte' => 20, 'prix_achat_ht' => 1.200, 'prix_vente_ht' => 2.000, 'taux_tva' => 19],
                ['nom' => 'Liquide vaisselle 750ml', 'reference' => 'HYG-031', 'description' => 'Détergent vaisselle', 'categorie' => 'Hygiène & entretien', 'unite_mesure' => 'pcs', 'quantite' => 60, 'seuil_alerte' => 15, 'prix_achat_ht' => 1.800, 'prix_vente_ht' => 3.000, 'taux_tva' => 19],
                ['nom' => 'Thon en conserve 160g', 'reference' => 'CONS-040', 'description' => 'Thon à l\'huile', 'categorie' => 'Conserves', 'unite_mesure' => 'pcs', 'quantite' => 110, 'seuil_alerte' => 25, 'prix_achat_ht' => 2.400, 'prix_vente_ht' => 3.600, 'taux_tva' => 7],
                ['nom' => 'Concentré de tomate 400g', 'reference' => 'CONS-041', 'description' => 'Double concentré', 'categorie' => 'Conserves', 'unite_mesure' => 'pcs', 'quantite' => 90, 'seuil_alerte' => 20, 'prix_achat_ht' => 1.500, 'prix_vente_ht' => 2.300, 'taux_tva' => 7],
            ],
        ];
    }

    // ── Menuiserie & Ébénisterie ─────────────────────────────────────────────

    private static function menuiserie(): array
    {
        return [
            'types' => [
                [
                    'nom' => 'Produit fini', 'icone' => '🚪',
                    'description' => 'Article fabriqué vendu au client (porte, fenêtre, meuble)',
                    'attributs' => [
                        ['nom' => 'matiere',   'label' => 'Matière',        'type_donnee' => 'select', 'obligatoire' => false, 'options_select' => 'Bois,Aluminium,PVC,Mixte'],
                        ['nom' => 'hauteur_cm', 'label' => 'Hauteur (cm)',   'type_donnee' => 'number', 'obligatoire' => false],
                        ['nom' => 'largeur_cm', 'label' => 'Largeur (cm)',   'type_donnee' => 'number', 'obligatoire' => false],
                        ['nom' => 'finition',  'label' => 'Finition',        'type_donnee' => 'text',   'obligatoire' => false],
                    ],
                ],
                [
                    'nom' => 'Matière première', 'icone' => '🪵',
                    'description' => 'Matériau brut utilisé en fabrication',
                    'attributs' => [
                        ['nom' => 'essence',     'label' => 'Essence / type', 'type_donnee' => 'text', 'obligatoire' => false],
                        ['nom' => 'epaisseur_mm', 'label' => 'Épaisseur (mm)', 'type_donnee' => 'number', 'obligatoire' => false],
                    ],
                ],
            ],
            'categories' => ['Bois & Panneaux', 'Quincaillerie', 'Portes', 'Fenêtres'],
            'products' => [
                ['nom' => 'Panneau MDF 18mm (2.8×2.07m)', 'reference' => 'BOIS-001', 'description' => 'Panneau medium pour meubles', 'categorie' => 'Bois & Panneaux', 'unite_mesure' => 'pcs', 'quantite' => 40, 'seuil_alerte' => 8, 'prix_achat_ht' => 45.000, 'prix_vente_ht' => 70.000, 'taux_tva' => 19],
                ['nom' => 'Planche de hêtre (2m)', 'reference' => 'BOIS-002', 'description' => 'Bois massif de hêtre', 'categorie' => 'Bois & Panneaux', 'unite_mesure' => 'm', 'quantite' => 120, 'seuil_alerte' => 25, 'prix_achat_ht' => 12.000, 'prix_vente_ht' => 20.000, 'taux_tva' => 19],
                ['nom' => 'Contreplaqué 10mm (2.5×1.25m)', 'reference' => 'BOIS-003', 'description' => 'Panneau contreplaqué', 'categorie' => 'Bois & Panneaux', 'unite_mesure' => 'pcs', 'quantite' => 30, 'seuil_alerte' => 6, 'prix_achat_ht' => 32.000, 'prix_vente_ht' => 52.000, 'taux_tva' => 19],
                ['nom' => 'Charnière inox (boîte 20)', 'reference' => 'QUINC-010', 'description' => 'Charnières pour portes', 'categorie' => 'Quincaillerie', 'unite_mesure' => 'boite', 'quantite' => 50, 'seuil_alerte' => 10, 'prix_achat_ht' => 14.000, 'prix_vente_ht' => 24.000, 'taux_tva' => 19],
                ['nom' => 'Serrure encastrable', 'reference' => 'QUINC-011', 'description' => 'Serrure à mortaiser', 'categorie' => 'Quincaillerie', 'unite_mesure' => 'pcs', 'quantite' => 60, 'seuil_alerte' => 12, 'prix_achat_ht' => 9.000, 'prix_vente_ht' => 18.000, 'taux_tva' => 19],
                ['nom' => 'Vis à bois 4×40 (boîte 200)', 'reference' => 'QUINC-012', 'description' => 'Vis tête fraisée', 'categorie' => 'Quincaillerie', 'unite_mesure' => 'boite', 'quantite' => 80, 'seuil_alerte' => 15, 'prix_achat_ht' => 6.000, 'prix_vente_ht' => 11.000, 'taux_tva' => 19],
                ['nom' => 'Vernis polyuréthane 5L', 'reference' => 'QUINC-013', 'description' => 'Finition bois intérieur', 'categorie' => 'Quincaillerie', 'unite_mesure' => 'pcs', 'quantite' => 25, 'seuil_alerte' => 6, 'prix_achat_ht' => 38.000, 'prix_vente_ht' => 60.000, 'taux_tva' => 19],
                ['nom' => 'Porte intérieure bois (83×204)', 'reference' => 'PORTE-020', 'description' => 'Porte alvéolaire prête à poser', 'categorie' => 'Portes', 'unite_mesure' => 'pcs', 'quantite' => 20, 'seuil_alerte' => 4, 'prix_achat_ht' => 120.000, 'prix_vente_ht' => 210.000, 'taux_tva' => 19],
                ['nom' => 'Porte d\'entrée blindée', 'reference' => 'PORTE-021', 'description' => 'Porte sécurisée avec serrure 3 points', 'categorie' => 'Portes', 'unite_mesure' => 'pcs', 'quantite' => 8, 'seuil_alerte' => 2, 'prix_achat_ht' => 650.000, 'prix_vente_ht' => 1050.000, 'taux_tva' => 19],
                ['nom' => 'Fenêtre alu double vitrage (120×120)', 'reference' => 'FEN-030', 'description' => 'Fenêtre 2 vantaux isolante', 'categorie' => 'Fenêtres', 'unite_mesure' => 'pcs', 'quantite' => 15, 'seuil_alerte' => 3, 'prix_achat_ht' => 280.000, 'prix_vente_ht' => 460.000, 'taux_tva' => 19],
                ['nom' => 'Fenêtre PVC (100×100)', 'reference' => 'FEN-031', 'description' => 'Fenêtre PVC blanc oscillo-battant', 'categorie' => 'Fenêtres', 'unite_mesure' => 'pcs', 'quantite' => 18, 'seuil_alerte' => 4, 'prix_achat_ht' => 190.000, 'prix_vente_ht' => 320.000, 'taux_tva' => 19],
            ],
        ];
    }

    // ── Électronique ─────────────────────────────────────────────────────────

    private static function electronique(): array
    {
        return [
            'types' => [
                [
                    'nom' => 'Appareil électronique', 'icone' => '💻',
                    'description' => 'Appareil avec numéro de série et garantie',
                    'attributs' => [
                        ['nom' => 'numero_serie',  'label' => 'N° de série',     'type_donnee' => 'text',   'obligatoire' => true],
                        ['nom' => 'garantie_mois', 'label' => 'Garantie (mois)', 'type_donnee' => 'number', 'obligatoire' => false],
                    ],
                ],
                [
                    'nom' => 'Accessoire', 'icone' => '🔌',
                    'description' => 'Câble, chargeur et périphérique',
                    'attributs' => [
                        ['nom' => 'compatibilite', 'label' => 'Compatibilité', 'type_donnee' => 'text', 'obligatoire' => false],
                        ['nom' => 'couleur',       'label' => 'Couleur',       'type_donnee' => 'text', 'obligatoire' => false],
                    ],
                ],
            ],
            'categories' => ['Smartphones & tablettes', 'Ordinateurs', 'TV & son', 'Accessoires'],
            'products' => [
                ['nom' => 'Smartphone 128 Go', 'reference' => 'TEL-001', 'description' => 'Téléphone Android double SIM', 'categorie' => 'Smartphones & tablettes', 'unite_mesure' => 'pcs', 'quantite' => 30, 'seuil_alerte' => 6, 'prix_achat_ht' => 650.000, 'prix_vente_ht' => 899.000, 'taux_tva' => 19],
                ['nom' => 'Tablette 10 pouces', 'reference' => 'TEL-002', 'description' => 'Tablette Wi-Fi 64 Go', 'categorie' => 'Smartphones & tablettes', 'unite_mesure' => 'pcs', 'quantite' => 20, 'seuil_alerte' => 5, 'prix_achat_ht' => 420.000, 'prix_vente_ht' => 599.000, 'taux_tva' => 19],
                ['nom' => 'Ordinateur portable 15"', 'reference' => 'ORD-010', 'description' => 'Laptop i5 8 Go RAM 512 Go SSD', 'categorie' => 'Ordinateurs', 'unite_mesure' => 'pcs', 'quantite' => 15, 'seuil_alerte' => 4, 'prix_achat_ht' => 1450.000, 'prix_vente_ht' => 1990.000, 'taux_tva' => 19],
                ['nom' => 'Souris sans fil', 'reference' => 'ORD-011', 'description' => 'Souris optique Bluetooth', 'categorie' => 'Ordinateurs', 'unite_mesure' => 'pcs', 'quantite' => 80, 'seuil_alerte' => 15, 'prix_achat_ht' => 18.000, 'prix_vente_ht' => 35.000, 'taux_tva' => 19],
                ['nom' => 'Clavier mécanique', 'reference' => 'ORD-012', 'description' => 'Clavier rétroéclairé', 'categorie' => 'Ordinateurs', 'unite_mesure' => 'pcs', 'quantite' => 40, 'seuil_alerte' => 8, 'prix_achat_ht' => 55.000, 'prix_vente_ht' => 95.000, 'taux_tva' => 19],
                ['nom' => 'Téléviseur LED 50"', 'reference' => 'TV-020', 'description' => 'Smart TV 4K UHD', 'categorie' => 'TV & son', 'unite_mesure' => 'pcs', 'quantite' => 12, 'seuil_alerte' => 3, 'prix_achat_ht' => 980.000, 'prix_vente_ht' => 1350.000, 'taux_tva' => 19],
                ['nom' => 'Casque Bluetooth', 'reference' => 'TV-021', 'description' => 'Casque sans fil réducteur de bruit', 'categorie' => 'TV & son', 'unite_mesure' => 'pcs', 'quantite' => 35, 'seuil_alerte' => 8, 'prix_achat_ht' => 75.000, 'prix_vente_ht' => 129.000, 'taux_tva' => 19],
                ['nom' => 'Chargeur USB-C 20W', 'reference' => 'ACC-030', 'description' => 'Chargeur rapide', 'categorie' => 'Accessoires', 'unite_mesure' => 'pcs', 'quantite' => 120, 'seuil_alerte' => 25, 'prix_achat_ht' => 12.000, 'prix_vente_ht' => 25.000, 'taux_tva' => 19],
                ['nom' => 'Câble HDMI 2m', 'reference' => 'ACC-031', 'description' => 'Câble HDMI haute vitesse', 'categorie' => 'Accessoires', 'unite_mesure' => 'pcs', 'quantite' => 100, 'seuil_alerte' => 20, 'prix_achat_ht' => 8.000, 'prix_vente_ht' => 18.000, 'taux_tva' => 19],
                ['nom' => 'Power bank 10000mAh', 'reference' => 'ACC-032', 'description' => 'Batterie externe', 'categorie' => 'Accessoires', 'unite_mesure' => 'pcs', 'quantite' => 60, 'seuil_alerte' => 12, 'prix_achat_ht' => 28.000, 'prix_vente_ht' => 49.000, 'taux_tva' => 19],
            ],
        ];
    }

    // ── Quincaillerie & BTP ──────────────────────────────────────────────────

    private static function quincaillerie(): array
    {
        return [
            'types' => [
                [
                    'nom' => 'Outil', 'icone' => '🔧',
                    'description' => 'Outillage à main ou électroportatif',
                    'attributs' => [
                        ['nom' => 'marque',        'label' => 'Marque',         'type_donnee' => 'text',    'obligatoire' => false],
                        ['nom' => 'electrique',    'label' => 'Électroportatif', 'type_donnee' => 'boolean', 'obligatoire' => false],
                    ],
                ],
                [
                    'nom' => 'Matériau', 'icone' => '🧱',
                    'description' => 'Matériau de construction vendu au détail',
                    'attributs' => [
                        ['nom' => 'unite_vente', 'label' => 'Unité de vente', 'type_donnee' => 'select', 'obligatoire' => false, 'options_select' => 'Unité,Mètre,Sac,Palette'],
                    ],
                ],
            ],
            'categories' => ['Outillage', 'Plomberie', 'Électricité', 'Peinture & finition'],
            'products' => [
                ['nom' => 'Perceuse à percussion 750W', 'reference' => 'OUT-001', 'description' => 'Perceuse électroportative', 'categorie' => 'Outillage', 'unite_mesure' => 'pcs', 'quantite' => 25, 'seuil_alerte' => 6, 'prix_achat_ht' => 95.000, 'prix_vente_ht' => 159.000, 'taux_tva' => 19],
                ['nom' => 'Jeu de tournevis (6 pcs)', 'reference' => 'OUT-002', 'description' => 'Tournevis plat et cruciforme', 'categorie' => 'Outillage', 'unite_mesure' => 'jeu', 'quantite' => 50, 'seuil_alerte' => 10, 'prix_achat_ht' => 15.000, 'prix_vente_ht' => 29.000, 'taux_tva' => 19],
                ['nom' => 'Marteau arrache-clou', 'reference' => 'OUT-003', 'description' => 'Marteau de charpentier', 'categorie' => 'Outillage', 'unite_mesure' => 'pcs', 'quantite' => 40, 'seuil_alerte' => 8, 'prix_achat_ht' => 12.000, 'prix_vente_ht' => 23.000, 'taux_tva' => 19],
                ['nom' => 'Tube PVC Ø100 (3m)', 'reference' => 'PLO-010', 'description' => 'Tuyau évacuation PVC', 'categorie' => 'Plomberie', 'unite_mesure' => 'pcs', 'quantite' => 60, 'seuil_alerte' => 12, 'prix_achat_ht' => 14.000, 'prix_vente_ht' => 24.000, 'taux_tva' => 19],
                ['nom' => 'Robinet mitigeur', 'reference' => 'PLO-011', 'description' => 'Mitigeur lavabo chromé', 'categorie' => 'Plomberie', 'unite_mesure' => 'pcs', 'quantite' => 30, 'seuil_alerte' => 6, 'prix_achat_ht' => 45.000, 'prix_vente_ht' => 79.000, 'taux_tva' => 19],
                ['nom' => 'Câble électrique 2.5mm² (100m)', 'reference' => 'ELE-020', 'description' => 'Câble rigide cuivre', 'categorie' => 'Électricité', 'unite_mesure' => 'rouleau', 'quantite' => 20, 'seuil_alerte' => 5, 'prix_achat_ht' => 85.000, 'prix_vente_ht' => 135.000, 'taux_tva' => 19],
                ['nom' => 'Disjoncteur 16A', 'reference' => 'ELE-021', 'description' => 'Disjoncteur modulaire', 'categorie' => 'Électricité', 'unite_mesure' => 'pcs', 'quantite' => 70, 'seuil_alerte' => 15, 'prix_achat_ht' => 9.000, 'prix_vente_ht' => 18.000, 'taux_tva' => 19],
                ['nom' => 'Prise murale + cache', 'reference' => 'ELE-022', 'description' => 'Prise 2P+T encastrable', 'categorie' => 'Électricité', 'unite_mesure' => 'pcs', 'quantite' => 90, 'seuil_alerte' => 20, 'prix_achat_ht' => 4.500, 'prix_vente_ht' => 9.500, 'taux_tva' => 19],
                ['nom' => 'Peinture acrylique blanche 15L', 'reference' => 'PEI-030', 'description' => 'Peinture murs et plafonds', 'categorie' => 'Peinture & finition', 'unite_mesure' => 'pot', 'quantite' => 35, 'seuil_alerte' => 8, 'prix_achat_ht' => 60.000, 'prix_vente_ht' => 95.000, 'taux_tva' => 19],
                ['nom' => 'Rouleau peinture + manche', 'reference' => 'PEI-031', 'description' => 'Kit rouleau anti-goutte', 'categorie' => 'Peinture & finition', 'unite_mesure' => 'pcs', 'quantite' => 80, 'seuil_alerte' => 18, 'prix_achat_ht' => 6.000, 'prix_vente_ht' => 13.000, 'taux_tva' => 19],
            ],
        ];
    }

    // ── Cosmétique & Beauté ──────────────────────────────────────────────────

    private static function cosmetique(): array
    {
        return [
            'types' => [
                [
                    'nom' => 'Produit cosmétique', 'icone' => '💄',
                    'description' => 'Soin, maquillage ou parfum',
                    'attributs' => [
                        ['nom' => 'marque',          'label' => 'Marque',            'type_donnee' => 'text', 'obligatoire' => false],
                        ['nom' => 'contenance_ml',   'label' => 'Contenance (ml)',   'type_donnee' => 'number', 'obligatoire' => false],
                        ['nom' => 'date_peremption', 'label' => 'Date de péremption', 'type_donnee' => 'date', 'obligatoire' => false],
                    ],
                ],
                [
                    'nom' => 'Accessoire beauté', 'icone' => '🪮',
                    'description' => 'Accessoire et outil de beauté',
                    'attributs' => [
                        ['nom' => 'matiere', 'label' => 'Matière', 'type_donnee' => 'text', 'obligatoire' => false],
                    ],
                ],
            ],
            'categories' => ['Soins visage', 'Maquillage', 'Parfums', 'Soins cheveux'],
            'products' => [
                ['nom' => 'Crème hydratante visage 50ml', 'reference' => 'SOI-001', 'description' => 'Soin hydratant quotidien', 'categorie' => 'Soins visage', 'unite_mesure' => 'pcs', 'quantite' => 50, 'seuil_alerte' => 10, 'prix_achat_ht' => 11.000, 'prix_vente_ht' => 22.000, 'taux_tva' => 19],
                ['nom' => 'Sérum anti-âge 30ml', 'reference' => 'SOI-002', 'description' => 'Sérum concentré', 'categorie' => 'Soins visage', 'unite_mesure' => 'pcs', 'quantite' => 30, 'seuil_alerte' => 6, 'prix_achat_ht' => 24.000, 'prix_vente_ht' => 45.000, 'taux_tva' => 19],
                ['nom' => 'Nettoyant moussant 150ml', 'reference' => 'SOI-003', 'description' => 'Gel nettoyant visage', 'categorie' => 'Soins visage', 'unite_mesure' => 'pcs', 'quantite' => 60, 'seuil_alerte' => 12, 'prix_achat_ht' => 8.000, 'prix_vente_ht' => 16.000, 'taux_tva' => 19],
                ['nom' => 'Rouge à lèvres mat', 'reference' => 'MAQ-010', 'description' => 'Tenue longue durée', 'categorie' => 'Maquillage', 'unite_mesure' => 'pcs', 'quantite' => 80, 'seuil_alerte' => 15, 'prix_achat_ht' => 6.500, 'prix_vente_ht' => 14.000, 'taux_tva' => 19],
                ['nom' => 'Fond de teint 30ml', 'reference' => 'MAQ-011', 'description' => 'Couvrance naturelle', 'categorie' => 'Maquillage', 'unite_mesure' => 'pcs', 'quantite' => 45, 'seuil_alerte' => 10, 'prix_achat_ht' => 13.000, 'prix_vente_ht' => 26.000, 'taux_tva' => 19],
                ['nom' => 'Mascara volume', 'reference' => 'MAQ-012', 'description' => 'Mascara noir intense', 'categorie' => 'Maquillage', 'unite_mesure' => 'pcs', 'quantite' => 70, 'seuil_alerte' => 14, 'prix_achat_ht' => 7.000, 'prix_vente_ht' => 15.000, 'taux_tva' => 19],
                ['nom' => 'Parfum femme 90ml', 'reference' => 'PAR-020', 'description' => 'Eau de parfum florale', 'categorie' => 'Parfums', 'unite_mesure' => 'pcs', 'quantite' => 25, 'seuil_alerte' => 5, 'prix_achat_ht' => 55.000, 'prix_vente_ht' => 99.000, 'taux_tva' => 19],
                ['nom' => 'Parfum homme 100ml', 'reference' => 'PAR-021', 'description' => 'Eau de toilette boisée', 'categorie' => 'Parfums', 'unite_mesure' => 'pcs', 'quantite' => 25, 'seuil_alerte' => 5, 'prix_achat_ht' => 52.000, 'prix_vente_ht' => 95.000, 'taux_tva' => 19],
                ['nom' => 'Shampooing 400ml', 'reference' => 'CHE-030', 'description' => 'Shampooing tous cheveux', 'categorie' => 'Soins cheveux', 'unite_mesure' => 'pcs', 'quantite' => 90, 'seuil_alerte' => 20, 'prix_achat_ht' => 5.000, 'prix_vente_ht' => 11.000, 'taux_tva' => 19],
                ['nom' => 'Masque capillaire 250ml', 'reference' => 'CHE-031', 'description' => 'Soin réparateur', 'categorie' => 'Soins cheveux', 'unite_mesure' => 'pcs', 'quantite' => 40, 'seuil_alerte' => 8, 'prix_achat_ht' => 9.000, 'prix_vente_ht' => 18.000, 'taux_tva' => 19],
            ],
        ];
    }

    // ── Défaut générique ─────────────────────────────────────────────────────

    private static function general(): array
    {
        return [
            'types' => [
                [
                    'nom' => 'Produit standard', 'icone' => '📦',
                    'description' => 'Article générique avec gestion de stock',
                    'attributs' => [
                        ['nom' => 'marque',   'label' => 'Marque',     'type_donnee' => 'text',   'obligatoire' => false],
                        ['nom' => 'poids_kg', 'label' => 'Poids (kg)', 'type_donnee' => 'number', 'obligatoire' => false],
                    ],
                ],
                [
                    'nom' => 'Consommable', 'icone' => '🧰',
                    'description' => 'Fourniture consommée régulièrement',
                    'attributs' => [
                        ['nom' => 'fournisseur', 'label' => 'Fournisseur', 'type_donnee' => 'text', 'obligatoire' => false],
                    ],
                ],
            ],
            'categories' => ['Général', 'Fournitures', 'Accessoires'],
            'products' => [
                ['nom' => 'Article A', 'reference' => 'GEN-001', 'description' => 'Produit de démonstration', 'categorie' => 'Général', 'unite_mesure' => 'pcs', 'quantite' => 100, 'seuil_alerte' => 20, 'prix_achat_ht' => 5.000, 'prix_vente_ht' => 9.000, 'taux_tva' => 19],
                ['nom' => 'Article B', 'reference' => 'GEN-002', 'description' => 'Produit de démonstration', 'categorie' => 'Général', 'unite_mesure' => 'pcs', 'quantite' => 80, 'seuil_alerte' => 15, 'prix_achat_ht' => 8.000, 'prix_vente_ht' => 14.000, 'taux_tva' => 19],
                ['nom' => 'Article C', 'reference' => 'GEN-003', 'description' => 'Produit de démonstration', 'categorie' => 'Général', 'unite_mesure' => 'pcs', 'quantite' => 60, 'seuil_alerte' => 12, 'prix_achat_ht' => 12.000, 'prix_vente_ht' => 20.000, 'taux_tva' => 19],
                ['nom' => 'Stylo bille (boîte 50)', 'reference' => 'FOUR-010', 'description' => 'Fourniture de bureau', 'categorie' => 'Fournitures', 'unite_mesure' => 'boite', 'quantite' => 40, 'seuil_alerte' => 10, 'prix_achat_ht' => 6.000, 'prix_vente_ht' => 11.000, 'taux_tva' => 19],
                ['nom' => 'Ramette papier A4', 'reference' => 'FOUR-011', 'description' => 'Papier 80g 500 feuilles', 'categorie' => 'Fournitures', 'unite_mesure' => 'pcs', 'quantite' => 50, 'seuil_alerte' => 12, 'prix_achat_ht' => 7.500, 'prix_vente_ht' => 12.000, 'taux_tva' => 19],
                ['nom' => 'Carton d\'emballage (lot 20)', 'reference' => 'ACC-020', 'description' => 'Cartons standards', 'categorie' => 'Accessoires', 'unite_mesure' => 'lot', 'quantite' => 30, 'seuil_alerte' => 8, 'prix_achat_ht' => 10.000, 'prix_vente_ht' => 16.000, 'taux_tva' => 19],
                ['nom' => 'Ruban adhésif (pack 6)', 'reference' => 'ACC-021', 'description' => 'Adhésif d\'emballage', 'categorie' => 'Accessoires', 'unite_mesure' => 'pack', 'quantite' => 45, 'seuil_alerte' => 10, 'prix_achat_ht' => 4.000, 'prix_vente_ht' => 7.500, 'taux_tva' => 19],
                ['nom' => 'Sac kraft (lot 100)', 'reference' => 'ACC-022', 'description' => 'Sacs papier', 'categorie' => 'Accessoires', 'unite_mesure' => 'lot', 'quantite' => 25, 'seuil_alerte' => 6, 'prix_achat_ht' => 9.000, 'prix_vente_ht' => 15.000, 'taux_tva' => 19],
            ],
        ];
    }
}
