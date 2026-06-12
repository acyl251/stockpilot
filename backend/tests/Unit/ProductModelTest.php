<?php

uses(\Tests\TestCase::class);

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;

beforeEach(function () {
    $plan = Plan::create(['nom' => 'Pro', 'max_utilisateurs' => 10, 'max_produits' => 2000, 'ia_activee' => true, 'prix_mensuel' => 149]);
    $org  = Organisation::create(['plan_id' => $plan->id, 'nom' => 'Org Test', 'email_contact' => 'o@test.tn', 'actif' => true, 'onboarding_complete' => true]);
    app()->instance('current_organisation_id', $org->id);
    $this->org = $org;
});

// UT-PROD-01: Calcul TTC avec TVA 19%
test('UT-PROD-01: prix_achat_ttc calculé correctement avec TVA 19%', function () {
    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Produit Test',
        'seuil_alerte'    => 5,
        'prix_achat_ht'   => 100.000,
        'taux_tva'        => 19.00,
        'prix_vente_ht'   => 130.000,
    ]);

    expect($product->fresh()->prix_achat_ttc)->toBe(119.0);
});

// UT-PROD-02: Calcul TTC avec TVA 0%
test('UT-PROD-02: prix_achat_ttc = prix_achat_ht quand TVA à 0%', function () {
    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Produit Exonéré',
        'seuil_alerte'    => 5,
        'prix_achat_ht'   => 50.000,
        'taux_tva'        => 0.00,
        'prix_vente_ht'   => 70.000,
    ]);

    expect($product->fresh()->prix_achat_ttc)->toBe(50.0);
});

// UT-PROD-03: Détection alerte quand quantité ≤ seuil
test('UT-PROD-03: en_alerte est true quand quantite <= seuil_alerte', function () {
    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Produit Alerte',
        'quantite'        => 15.000,
        'seuil_alerte'    => 20.000,
        'prix_achat_ht'   => 10,
        'prix_vente_ht'   => 15,
    ]);

    expect($product->fresh()->en_alerte)->toBeTrue()
        ->and($product->fresh()->statut)->toBe('Alerte');
});

// UT-PROD-03b: Pas d'alerte quand quantite > seuil
test('UT-PROD-03b: en_alerte est false quand quantite > seuil_alerte', function () {
    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Produit OK',
        'quantite'        => 50.000,
        'seuil_alerte'    => 20.000,
        'prix_achat_ht'   => 10,
        'prix_vente_ht'   => 15,
    ]);

    expect($product->fresh()->en_alerte)->toBeFalse()
        ->and($product->fresh()->statut)->toBe('En stock');
});

// UT-PROD-04: Rupture quand quantite = 0
test('UT-PROD-04: en_rupture est true quand quantite = 0', function () {
    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Produit Rupture',
        'quantite'        => 0.000,
        'seuil_alerte'    => 10.000,
        'prix_achat_ht'   => 10,
        'prix_vente_ht'   => 15,
    ]);

    expect($product->fresh()->en_rupture)->toBeTrue()
        ->and($product->fresh()->statut)->toBe('Rupture');
});

// UT-PROD-05: TVA 7% (produits alimentaires)
test('UT-PROD-05: prix_achat_ttc correct avec TVA 7%', function () {
    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Produit Alimentaire',
        'seuil_alerte'    => 5,
        'prix_achat_ht'   => 10.000,
        'taux_tva'        => 7.00,
        'prix_vente_ht'   => 15.000,
    ]);

    expect($product->fresh()->prix_achat_ttc)->toBe(10.7);
});

// UT-PROD-06: prix_vente_ttc calculé correctement
test('UT-PROD-06: prix_vente_ttc calculé correctement', function () {
    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Produit Vente',
        'seuil_alerte'    => 5,
        'prix_achat_ht'   => 100.000,
        'taux_tva'        => 19.00,
        'prix_vente_ht'   => 150.000,
    ]);

    expect($product->fresh()->prix_vente_ttc)->toBe(178.5);
});

// UT-PROD-07: Attributs JSON stockés et restitués
test('UT-PROD-07: attributs JSON stockés et restitués correctement', function () {
    $attrs = ['date_expiration' => '2027-06-30', 'numero_lot' => 'LOT-042'];

    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Médicament Test',
        'seuil_alerte'    => 5,
        'prix_achat_ht'   => 2.500,
        'taux_tva'        => 19.00,
        'prix_vente_ht'   => 4.000,
        'attributs'       => $attrs,
    ]);

    expect($product->fresh()->attributs)->toMatchArray($attrs);
});
