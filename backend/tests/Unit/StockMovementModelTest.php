<?php

uses(\Tests\TestCase::class);

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $plan = Plan::create(['nom' => 'Pro', 'max_utilisateurs' => 10, 'max_produits' => 2000, 'ia_activee' => true, 'prix_mensuel' => 149]);
    $org  = Organisation::create(['plan_id' => $plan->id, 'nom' => 'Org Test', 'email_contact' => 'o@test.tn', 'actif' => true, 'onboarding_complete' => true]);
    $user = User::create(['organisation_id' => $org->id, 'nom' => 'Test', 'prenom' => 'User', 'email' => 'u@t.tn', 'password' => Hash::make('p'), 'role' => 'admin', 'actif' => true]);

    app()->instance('current_organisation_id', $org->id);
    app()->instance('current_user', $user);

    $this->product = Product::create([
        'organisation_id' => $org->id,
        'nom'             => 'Widget',
        'reference'       => 'W-001',
        'quantite'        => 50.000,
        'seuil_alerte'    => 10.000,
        'prix_achat_ht'   => 5.000,
        'prix_vente_ht'   => 8.000,
    ]);
    $this->user    = $user;
    $this->service = app(StockService::class);
});

// Helper: decimal:3 cast returns string from DB — cast to float for comparison
function qty($product): float { return (float) $product->quantite; }
function mvQty($movement, string $field): float { return (float) $movement->$field; }

// UT-MV-01: Entrée augmente la quantité
test('UT-MV-01: entree augmente la quantite du produit', function () {
    $this->service->createMovement($this->product->id, $this->user->id, 'entree', 30);
    expect(qty($this->product->fresh()))->toBe(80.0);
});

// UT-MV-02: La sortie enregistre quantite_avant et quantite_apres
test('UT-MV-02: sortie enregistre quantite_avant et quantite_apres correctement', function () {
    $this->service->createMovement($this->product->id, $this->user->id, 'sortie', 20);

    $movement = StockMovement::withoutGlobalScopes()->latest()->first();

    expect(mvQty($movement, 'quantite_avant'))->toBe(50.0)
        ->and(mvQty($movement, 'quantite_apres'))->toBe(30.0);
});

// UT-MV-03: Le mouvement est immuable après création
test('UT-MV-03: un mouvement ne peut pas etre modifie apres creation', function () {
    $this->service->createMovement($this->product->id, $this->user->id, 'entree', 10);

    $movement = StockMovement::withoutGlobalScopes()->latest()->first();
    $movement->update(['quantite' => 999]);

    expect(mvQty(StockMovement::withoutGlobalScopes()->find($movement->id), 'quantite_apres'))
        ->not->toBe(999.0);
});

// UT-MV-04: Ajustement fixe la quantité absolue
test('UT-MV-04: ajustement fixe une quantite absolue', function () {
    $this->service->createMovement($this->product->id, $this->user->id, 'ajustement', 42);
    expect(qty($this->product->fresh()))->toBe(42.0);
});

// UT-MV-05: Sortie avec stock insuffisant lève une exception
test('UT-MV-05: sortie superieure au stock leve une ValidationException', function () {
    expect(fn () => $this->service->createMovement($this->product->id, $this->user->id, 'sortie', 999))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

// UT-MV-06: Chaîne de mouvements successifs
test('UT-MV-06: plusieurs mouvements successifs accumulent correctement', function () {
    $this->service->createMovement($this->product->id, $this->user->id, 'entree', 20);   // 70
    $this->service->createMovement($this->product->id, $this->user->id, 'sortie', 10);   // 60
    $this->service->createMovement($this->product->id, $this->user->id, 'entree', 5);    // 65

    expect(qty($this->product->fresh()))->toBe(65.0);
});
