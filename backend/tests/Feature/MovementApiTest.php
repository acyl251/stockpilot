<?php

uses(\Tests\TestCase::class);

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;
use App\Models\StockMovement;
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

    $this->product = Product::create([
        'organisation_id' => $this->org->id,
        'nom'             => 'Widget',
        'reference'       => 'W-001',
        'quantite'        => 30.000,
        'seuil_alerte'    => 10.000,
        'prix_achat_ht'   => 5.000,
        'prix_vente_ht'   => 8.000,
    ]);
});

// IT-MV-01: EntrÃ©e de stock â†' quantitÃ© augmente
test('IT-MV-01: enregistrement entree augmente la quantite du produit', function () {
    $response = $this->withJwt($this->user)->postJson('/api/movements', [
        'product_id'     => $this->product->id,
        'type_mouvement' => 'entree',
        'quantite'       => 20,
        'note'           => 'RÃ©ception BL-042',
    ]);

    $response->assertCreated();

    expect((float) $this->product->fresh()->quantite)->toBe(50.0);
});

// IT-MV-02: Sortie â†' quantitÃ© diminue
test('IT-MV-02: enregistrement sortie diminue la quantite du produit', function () {
    $response = $this->withJwt($this->user)->postJson('/api/movements', [
        'product_id'     => $this->product->id,
        'type_mouvement' => 'sortie',
        'quantite'       => 10,
    ]);

    $response->assertCreated();

    expect((float) $this->product->fresh()->quantite)->toBe(20.0);
});

// IT-MV-03: Ajustement â†' quantitÃ© fixÃ©e
test('IT-MV-03: ajustement fixe la quantite a la valeur donnee', function () {
    $response = $this->withJwt($this->user)->postJson('/api/movements', [
        'product_id'     => $this->product->id,
        'type_mouvement' => 'ajustement',
        'quantite'       => 55,
    ]);

    $response->assertCreated();

    expect((float) $this->product->fresh()->quantite)->toBe(55.0);
});

// IT-MV-04: Sortie avec stock insuffisant â†' 422
test('IT-MV-04: sortie superieure au stock retourne 422', function () {
    $response = $this->withJwt($this->user)->postJson('/api/movements', [
        'product_id'     => $this->product->id,
        'type_mouvement' => 'sortie',
        'quantite'       => 999,
    ]);

    $response->assertUnprocessable();
});

// IT-MV-05: quantite_avant et quantite_apres enregistrÃ©es
test('IT-MV-05: mouvement enregistre quantite_avant et quantite_apres', function () {
    $this->withJwt($this->user)->postJson('/api/movements', [
        'product_id'     => $this->product->id,
        'type_mouvement' => 'entree',
        'quantite'       => 10,
    ]);

    $movement = StockMovement::withoutGlobalScopes()->latest()->first();

    expect((float) $movement->quantite_avant)->toBe(30.0)
        ->and((float) $movement->quantite_apres)->toBe(40.0);
});

// IT-MV-06: Liste des mouvements filtrÃ©e par tenant
test('IT-MV-06: GET movements retourne seulement les mouvements du tenant', function () {
    $this->withJwt($this->user)->postJson('/api/movements', [
        'product_id'     => $this->product->id,
        'type_mouvement' => 'entree',
        'quantite'       => 5,
    ]);

    $response = $this->withJwt($this->user)->getJson('/api/movements');

    $response->assertOk()
        ->assertJsonStructure(['data']);

    // Tous les mouvements doivent appartenir au produit de ce tenant
    collect($response->json('data'))->each(function ($m) {
        expect($m['product_id'])->toBe($this->product->id);
    });
});

// IT-MV-07: Type de mouvement invalide â†' 422
test('IT-MV-07: type mouvement invalide retourne 422', function () {
    $response = $this->withJwt($this->user)->postJson('/api/movements', [
        'product_id'     => $this->product->id,
        'type_mouvement' => 'vol',
        'quantite'       => 5,
    ]);

    $response->assertUnprocessable();
});
