<?php

uses(\Tests\TestCase::class);

use App\Models\Category;
use App\Models\Composition;
use App\Models\Product;

// ── Garde restauration ────────────────────────────────────────────────────────

test('un org commerce reçoit 403 en tentant de créer un produit composé', function () {
    $org  = $this->createOrg('Commerce');
    $user = $this->actingAsOrg($org);

    Category::create(['organisation_id' => $org->id, 'nom' => 'Test', 'couleur' => '#000']);

    $this->withJwt($user)->postJson('/api/products', [
        'nom'           => 'Pizza',
        'seuil_alerte'  => 1,
        'prix_achat_ht' => 3,
        'prix_vente_ht' => 10,
        'type'          => 'compose',
    ])->assertForbidden();
});

test('un org commerce reçoit 403 en accédant aux routes composition', function () {
    $org  = $this->createOrg('Commerce2');
    $user = $this->actingAsOrg($org);
    $cat  = Category::create(['organisation_id' => $org->id, 'nom' => 'Test', 'couleur' => '#000']);
    $p    = Product::create([
        'organisation_id' => $org->id, 'category_id' => $cat->id,
        'nom' => 'Test', 'quantite' => 10, 'seuil_alerte' => 1,
        'prix_achat_ht' => 1, 'taux_tva' => 0, 'prix_vente_ht' => 2, 'actif' => true,
    ]);

    $this->withJwt($user)->getJson("/api/products/{$p->id}/composition")->assertForbidden();
    $this->withJwt($user)->postJson("/api/products/{$p->id}/composition", [])->assertForbidden();
});

// ── CRUD composition ──────────────────────────────────────────────────────────

test('on peut ajouter / lire / modifier / supprimer une ligne de recette', function () {
    $org  = $this->createOrg('Resto CRUD');
    $org->update(['secteur' => 'restauration']);
    $user = $this->actingAsOrg($org);

    $cat    = Category::create(['organisation_id' => $org->id, 'nom' => 'Test', 'couleur' => '#000']);
    $pizza  = Product::create([
        'organisation_id' => $org->id, 'category_id' => $cat->id,
        'nom' => 'Pizza Margarita', 'quantite' => 0, 'seuil_alerte' => 0,
        'prix_achat_ht' => 3, 'taux_tva' => 7, 'prix_vente_ht' => 12, 'actif' => true, 'type' => 'compose',
    ]);
    $farine = Product::create([
        'organisation_id' => $org->id, 'category_id' => $cat->id,
        'nom' => 'Farine', 'quantite' => 50, 'seuil_alerte' => 5,
        'prix_achat_ht' => 1, 'taux_tva' => 0, 'prix_vente_ht' => 2, 'actif' => true,
    ]);

    // POST — ajouter une ligne
    $res = $this->withJwt($user)->postJson("/api/products/{$pizza->id}/composition", [
        'composant_id' => $farine->id,
        'quantite'     => 0.25,
        'unite'        => 'kg',
    ]);
    $res->assertCreated()->assertJsonPath('composant_id', $farine->id);

    $compId = $res->json('id');

    // GET — lire la recette
    $this->withJwt($user)->getJson("/api/products/{$pizza->id}/composition")
        ->assertOk()
        ->assertJsonPath('lignes.0.quantite', 0.25);

    // PATCH — modifier la quantité
    $this->withJwt($user)->patchJson("/api/products/{$pizza->id}/composition/{$compId}", [
        'quantite' => 0.3,
    ])->assertOk()->assertJsonPath('quantite', '0.300');

    // DELETE
    $this->withJwt($user)->deleteJson("/api/products/{$pizza->id}/composition/{$compId}")
        ->assertOk();

    expect(Composition::find($compId))->toBeNull();
});

// ── Vente d'un produit composé ────────────────────────────────────────────────

test('vendre un produit composé décrémente les ingrédients et non le composé', function () {
    $org  = $this->createOrg('Resto Vente');
    $org->update(['secteur' => 'restauration']);
    $user = $this->actingAsOrg($org);

    $cat    = Category::create(['organisation_id' => $org->id, 'nom' => 'Test', 'couleur' => '#000']);
    $pizza  = Product::create([
        'organisation_id' => $org->id, 'category_id' => $cat->id,
        'nom' => 'Pizza', 'quantite' => 0, 'seuil_alerte' => 0,
        'prix_achat_ht' => 3, 'taux_tva' => 0, 'prix_vente_ht' => 12, 'actif' => true, 'type' => 'compose',
    ]);
    $farine = Product::create([
        'organisation_id' => $org->id, 'category_id' => $cat->id,
        'nom' => 'Farine', 'quantite' => 10, 'seuil_alerte' => 1,
        'prix_achat_ht' => 1, 'taux_tva' => 0, 'prix_vente_ht' => 2, 'actif' => true,
    ]);
    $tomate = Product::create([
        'organisation_id' => $org->id, 'category_id' => $cat->id,
        'nom' => 'Tomate', 'quantite' => 5, 'seuil_alerte' => 1,
        'prix_achat_ht' => 0.5, 'taux_tva' => 0, 'prix_vente_ht' => 1, 'actif' => true,
    ]);

    // Recette : 1 pizza = 0.2 kg farine + 0.1 kg tomate
    Composition::create([
        'organisation_id'    => $org->id,
        'produit_compose_id' => $pizza->id,
        'composant_id'       => $farine->id,
        'quantite'           => 0.2,
        'unite'              => 'kg',
    ]);
    Composition::create([
        'organisation_id'    => $org->id,
        'produit_compose_id' => $pizza->id,
        'composant_id'       => $tomate->id,
        'quantite'           => 0.1,
        'unite'              => 'kg',
    ]);

    // Vendre 2 pizzas
    $this->withJwt($user)->postJson('/api/sales', [
        'items'         => [['product_id' => $pizza->id, 'quantite' => 2]],
        'mode_paiement' => 'especes',
        'montant_paye'  => 24,
    ])->assertCreated();

    // Le stock du composé ne change pas
    expect((float) $pizza->fresh()->quantite)->toBe(0.0);
    // Farine : 10 - (0.2 × 2) = 9.6
    expect((float) $farine->fresh()->quantite)->toBe(9.6);
    // Tomate : 5 - (0.1 × 2) = 4.8
    expect((float) $tomate->fresh()->quantite)->toBe(4.8);
});

test('les produits simples en commerce se comportent exactement comme avant', function () {
    $org  = $this->createOrg('Commerce Simple');
    $user = $this->actingAsOrg($org);

    $cat = Category::create(['organisation_id' => $org->id, 'nom' => 'Test', 'couleur' => '#000']);
    $p   = Product::create([
        'organisation_id' => $org->id, 'category_id' => $cat->id,
        'nom' => 'Article', 'quantite' => 10, 'seuil_alerte' => 1,
        'prix_achat_ht' => 5, 'taux_tva' => 0, 'prix_vente_ht' => 10, 'actif' => true,
    ]);

    $this->withJwt($user)->postJson('/api/sales', [
        'items'         => [['product_id' => $p->id, 'quantite' => 3]],
        'mode_paiement' => 'especes',
        'montant_paye'  => 30,
    ])->assertCreated();

    expect((float) $p->fresh()->quantite)->toBe(7.0);
});

test('une vente d\'un produit simple échoue si stock insuffisant', function () {
    $org  = $this->createOrg('Commerce Rupture');
    $user = $this->actingAsOrg($org);

    $cat = Category::create(['organisation_id' => $org->id, 'nom' => 'Test', 'couleur' => '#000']);
    $p   = Product::create([
        'organisation_id' => $org->id, 'category_id' => $cat->id,
        'nom' => 'Article rare', 'quantite' => 1, 'seuil_alerte' => 0,
        'prix_achat_ht' => 5, 'taux_tva' => 0, 'prix_vente_ht' => 10, 'actif' => true,
    ]);

    $this->withJwt($user)->postJson('/api/sales', [
        'items'         => [['product_id' => $p->id, 'quantite' => 5]],
        'mode_paiement' => 'especes',
        'montant_paye'  => 50,
    ])->assertUnprocessable();
});
