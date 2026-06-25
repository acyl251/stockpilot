<?php

uses(\Tests\TestCase::class);

use App\Models\Category;
use App\Models\Product;

beforeEach(function () {
    $this->org  = $this->createOrg('Boutique Marge');
    $this->user = $this->actingAsOrg($this->org);

    $cat = Category::create(['organisation_id' => $this->org->id, 'nom' => 'Divers', 'couleur' => '#000']);
    // Coût 60, vente 100 HT → marge unitaire 40
    $this->product = Product::create([
        'organisation_id' => $this->org->id, 'category_id' => $cat->id,
        'nom' => 'Article A', 'reference' => 'A-001', 'quantite' => 50, 'seuil_alerte' => 2,
        'unite_mesure' => 'unité', 'prix_achat_ht' => 60, 'taux_tva' => 0, 'prix_vente_ht' => 100, 'actif' => true,
    ]);
});

test('la rentabilité du mois calcule la marge brute et le taux', function () {
    // Vend 3 unités → CA HT 300, coût 180, marge 120, taux 40%
    $this->withJwt($this->user)->postJson('/api/sales', [
        'items' => [['product_id' => $this->product->id, 'quantite' => 3]],
        'mode_paiement' => 'especes', 'montant_paye' => 300,
    ])->assertCreated();

    $res = $this->withJwt($this->user)->getJson('/api/dashboard');

    $res->assertOk()
        ->assertJsonPath('rentabilite.ca_ht_mois', fn($v) => (float) $v === 300.0)
        ->assertJsonPath('rentabilite.marge_mois', fn($v) => (float) $v === 120.0)
        ->assertJsonPath('rentabilite.marge_pct', fn($v) => (float) $v === 40.0);

    expect($res->json('rentabilite.top_produits.0.nom'))->toBe('Article A');
});

test('la marge reste figée même si le coût du produit change ensuite', function () {
    $this->withJwt($this->user)->postJson('/api/sales', [
        'items' => [['product_id' => $this->product->id, 'quantite' => 2]],
        'mode_paiement' => 'especes', 'montant_paye' => 200,
    ])->assertCreated();

    // Le fournisseur augmente le coût après la vente
    $this->product->update(['prix_achat_ht' => 90]);

    // La marge de la vente passée doit rester 2 × (100 − 60) = 80
    $res = $this->withJwt($this->user)->getJson('/api/dashboard');
    $res->assertOk()->assertJsonPath('rentabilite.marge_mois', fn($v) => (float) $v === 80.0);
});
