<?php

uses(\Tests\TestCase::class);

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $plan       = Plan::create(['nom' => 'Pro', 'max_utilisateurs' => 10, 'max_produits' => 2000, 'ia_activee' => true, 'prix_mensuel' => 149]);
    $this->org  = Organisation::create(['plan_id' => $plan->id, 'nom' => 'Test Org', 'email_contact' => 'org@test.tn', 'actif' => true, 'onboarding_complete' => true]);
    $this->user = User::create([
        'organisation_id' => $this->org->id,
        'nom' => 'Admin', 'prenom' => 'Test',
        'email'           => 'admin@test.tn',
        'password'        => Hash::make('Password123!'),
        'role'            => 'admin',
        'actif'           => true,
    ]);

    app()->instance('current_organisation_id', $this->org->id);
    app()->instance('current_user', $this->user);
});

// IT-PROD-01: CrÃ©ation produit basique â†' 201
test('IT-PROD-01: creation produit basique retourne 201 avec TTC calcule', function () {
    $response = $this->withJwt($this->user)->postJson('/api/products', [
        'nom'           => 'Widget Test',
        'seuil_alerte'  => 10,
        'prix_achat_ht' => 100.000,
        'taux_tva'      => 19,
        'prix_vente_ht' => 130.000,
    ]);

    $response->assertCreated();
    $json = $response->json();
    $product = $json['data'] ?? $json;
    expect($product['nom'])->toBe('Widget Test')
        ->and((float) $product['prix_achat_ttc'])->toBe(119.0);
});

// IT-PROD-02: TVA 7% â†' prix_achat_ttc correct
test('IT-PROD-02: creation avec TVA 7 pct calcule correctement le TTC', function () {
    $response = $this->withJwt($this->user)->postJson('/api/products', [
        'nom'           => 'Produit Alimentaire',
        'seuil_alerte'  => 5,
        'prix_achat_ht' => 10.000,
        'taux_tva'      => 7,
        'prix_vente_ht' => 15.000,
    ]);

    $response->assertCreated();
    $json = $response->json();
    $product = $json['data'] ?? $json;
    expect((float) $product['prix_achat_ttc'])->toBe(10.7);
});

// IT-PROD-03: TVA 0% â†' TTC = HT
test('IT-PROD-03: creation avec TVA 0 pct retourne TTC identique au HT', function () {
    $response = $this->withJwt($this->user)->postJson('/api/products', [
        'nom'           => 'Produit ExonÃ©rÃ©',
        'seuil_alerte'  => 2,
        'prix_achat_ht' => 50.000,
        'taux_tva'      => 0,
        'prix_vente_ht' => 60.000,
    ]);

    $response->assertCreated();
    $json = $response->json();
    $product = $json['data'] ?? $json;
    expect((float) $product['prix_achat_ttc'])->toBe(50.0);
});

// IT-PROD-04: Champs obligatoires manquants â†' 422
test('IT-PROD-04: champs obligatoires manquants retournent 422', function () {
    $response = $this->withJwt($this->user)->postJson('/api/products', [
        'description' => 'Pas de nom ni de prix',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['nom', 'prix_achat_ht', 'prix_vente_ht', 'seuil_alerte']);
});

// IT-PROD-05: Liste produits paginÃ©e
test('IT-PROD-05: liste produits retourne une collection paginee', function () {
    Product::create(['organisation_id' => $this->org->id, 'nom' => 'P1', 'seuil_alerte' => 5, 'prix_achat_ht' => 10, 'prix_vente_ht' => 15]);
    Product::create(['organisation_id' => $this->org->id, 'nom' => 'P2', 'seuil_alerte' => 5, 'prix_achat_ht' => 20, 'prix_vente_ht' => 30]);

    $response = $this->withJwt($this->user)->getJson('/api/products');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonCount(2, 'data');
});

// IT-PROD-06: RÃ©cupÃ©ration d'un produit par ID
test('IT-PROD-06: GET product par ID retourne le produit', function () {
    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Produit Get',
        'seuil_alerte'    => 5,
        'prix_achat_ht'   => 10,
        'prix_vente_ht'   => 15,
    ]);

    $response = $this->withJwt($this->user)->getJson("/api/products/{$product->id}");

    $response->assertOk();
    $json = $response->json();
    $p = $json['data'] ?? $json;
    expect($p['id'])->toBe($product->id)
        ->and($p['nom'])->toBe('Produit Get');
});

// IT-PROD-07: Mise Ã  jour d'un produit
test('IT-PROD-07: PATCH produit met a jour les champs envoyes', function () {
    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Avant Update',
        'seuil_alerte'    => 5,
        'prix_achat_ht'   => 10,
        'prix_vente_ht'   => 15,
    ]);

    $response = $this->withJwt($this->user)->patchJson("/api/products/{$product->id}", [
        'nom' => 'Apres Update',
    ]);

    $response->assertOk();
    $json = $response->json();
    $p = $json['data'] ?? $json;
    expect($p['nom'])->toBe('Apres Update');
    expect($product->fresh()->nom)->toBe('Apres Update');
});

// IT-PROD-08: DÃ©sactivation (soft delete) â†' actif = false
test('IT-PROD-08: DELETE produit desactive le produit sans le supprimer', function () {
    $product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'A Desactiver',
        'seuil_alerte'    => 5,
        'prix_achat_ht'   => 10,
        'prix_vente_ht'   => 15,
    ]);

    $this->withJwt($this->user)->deleteJson("/api/products/{$product->id}")->assertOk();

    expect($product->fresh()->actif)->toBeFalse();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'actif' => false]);
});

// IT-PROD-09: organisation_id injectÃ© ignorÃ© â€" toujours celui du JWT
test('IT-PROD-09: organisation_id injecte dans la requete est ignore', function () {
    $plan2 = Plan::create(['nom' => 'Pro2', 'max_utilisateurs' => 5, 'max_produits' => 100, 'ia_activee' => false, 'prix_mensuel' => 49]);
    $org2  = Organisation::create(['plan_id' => $plan2->id, 'nom' => 'Org Pirate', 'email_contact' => 'p@p.tn', 'actif' => true, 'onboarding_complete' => true]);

    $response = $this->withJwt($this->user)->postJson('/api/products', [
        'organisation_id' => $org2->id,
        'nom'             => 'Injection Test',
        'seuil_alerte'    => 5,
        'prix_achat_ht'   => 10,
        'prix_vente_ht'   => 15,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('products', [
        'nom'             => 'Injection Test',
        'organisation_id' => $this->org->id,
    ]);
});
