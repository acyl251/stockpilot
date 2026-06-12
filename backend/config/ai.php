<?php

return [
    'model'      => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'max_tokens'            => (int) env('OPENAI_MAX_TOKENS', 1024),
    'max_tokens_products'   => (int) env('OPENAI_MAX_TOKENS_PRODUCTS', 2500),
    'max_tokens_catalog'    => (int) env('OPENAI_MAX_TOKENS_CATALOG', 3800),

    'cache' => [
        'ttl_suggestions' => (int) env('CACHE_TTL_SUGGESTIONS', 21600),  // 6h
        'ttl_forecasts'   => (int) env('CACHE_TTL_FORECASTS', 86400),    // 24h
        'ttl_anomalies'   => (int) env('CACHE_TTL_ANOMALIES', 3600),     // 1h
    ],

    'prompts' => [
        'onboarding' => 'Tu es un expert en gestion de stocks pour les PME tunisiennes. '
            . 'Propose des types de produits adaptés au secteur "{{sector}}" avec leurs attributs personnalisés. '
            . 'Réponds uniquement en JSON strict.',

        'onboarding_products' => 'Tu es un expert en gestion de stocks pour les PME tunisiennes. '
            . 'Pour le secteur "{{sector}}", génère une liste réaliste de 8 à 15 produits typiques que cette entreprise gère en stock. '
            . 'Pour chaque produit inclure : nom, reference (code court unique ex: FAR-001), description (courte), '
            . 'categorie (nom de la catégorie), unite_mesure (ex: pcs, kg, L, boite), '
            . 'quantite (stock initial réaliste entier), seuil_alerte (seuil bas réaliste entier), '
            . 'prix_achat_ht (prix en dinars tunisiens nombre décimal), prix_vente_ht (prix vente HT nombre décimal), '
            . 'taux_tva (entier : 0, 7 ou 19). '
            . 'Groupe les produits par catégorie logique. '
            . 'Réponds UNIQUEMENT en JSON strict avec deux clés : "categories" (tableau de strings) et "products" (tableau d\'objets).',

        // Catalogue complet en un seul appel : types + catégories + produits
        'onboarding_full' => 'Tu es un expert en gestion de stocks pour les PME tunisiennes. '
            . 'Pour le secteur "{{sector}}", prépare un catalogue de démarrage COMPLET et réaliste. '
            . 'Tu dois répondre UNIQUEMENT en JSON strict avec EXACTEMENT trois clés : "types", "categories", "products".'
            . "\n\n"
            . '1) "types" : tableau de 2 à 4 types de produits adaptés au secteur. '
            . 'Chaque type = objet { "nom", "icone" (un emoji), "description" (courte), '
            . '"attributs": tableau d\'objets { "nom" (snake_case), "label", '
            . '"type_donnee" (un de: text,number,date,boolean,select), "obligatoire" (booléen), '
            . '"options_select" (string CSV uniquement si type_donnee=select, sinon omettre) } }.'
            . "\n\n"
            . '2) "categories" : tableau de strings (noms de catégories logiques pour ce secteur).'
            . "\n\n"
            . '3) "products" : tableau de 8 à 15 produits typiques. '
            . 'Chaque produit = objet { "nom", "reference" (code court unique ex: FAR-001), "description" (courte), '
            . '"categorie" (doit correspondre à une des "categories"), "unite_mesure" (ex: pcs, kg, L, boite), '
            . '"quantite" (stock initial réaliste entier), "seuil_alerte" (seuil bas réaliste entier), '
            . '"prix_achat_ht" (dinars tunisiens, nombre décimal), "prix_vente_ht" (nombre décimal, supérieur au prix d\'achat), '
            . '"taux_tva" (entier : 0, 7 ou 19) }.'
            . "\n\n"
            . 'Assure-toi que chaque produit référence une catégorie présente dans "categories". '
            . 'Réponds en français, en JSON strict, sans texte autour.',

        'reorder' => 'Analyse ces données de stock et suggère des quantités de réapprovisionnement optimales. '
            . 'Tiens compte des tendances de consommation. Réponds uniquement en JSON strict.',

        'anomaly' => 'Détecte les anomalies dans ces mouvements de stock selon la règle des 3 écarts-types (3σ). '
            . 'Identifie les valeurs aberrantes et leur niveau de sévérité. Réponds uniquement en JSON strict.',

        'forecast' => 'Génère une prévision de demande sur 30 jours pour ce produit basée sur l\'historique fourni. '
            . 'Réponds uniquement en JSON strict avec des dates et quantités quotidiennes.',

        'kpis' => 'Calcule des KPIs prédictifs pour ce tableau de bord de gestion de stocks. '
            . 'Inclus les tendances et alertes prévisionnelles. Réponds uniquement en JSON strict.',
    ],
];
