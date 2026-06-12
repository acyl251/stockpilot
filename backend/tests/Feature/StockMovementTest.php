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
    $plan = Plan::create(['nom' => 'Pro', 'max_utilisateurs' => 10, 'max_produits' => 2000, 'ia_activee' => 1, 'prix_mensuel' => 149]);
    $org  = Organisation::create(['plan_id' => $plan->id, 'nom' => 'Test Org', 'email_contact' => 'test@test.tn', 'actif' => 1, 'onboarding_complete' => 1]);
    $user = User::create(['organisation_id' => $org->id, 'nom' => 'Test', 'prenom' => 'User', 'email' => 'u@t.tn', 'password' => Hash::make('p'), 'role' => 'admin', 'actif' => 1]);

    app()->instance('current_organisation_id', $org->id);
    app()->instance('current_user', $user);

    $this->product = Product::create(['organisation_id' => $org->id, 'nom' => 'Widget', 'reference' => 'W-001', 'quantite' => 100, 'seuil_alerte' => 10, 'prix_achat_ht' => 5, 'prix_vente_ht' => 8]);
    $this->user    = $user;
    $this->service = app(StockService::class);
});

test('FT-01: entree movement increases stock quantity', function () {
    $this->service->createMovement($this->product->id, $this->user->id, 'entree', 50);
    expect((float) $this->product->fresh()->quantite)->toBe(150.0);
});

test('FT-02: sortie movement decreases stock quantity', function () {
    $this->service->createMovement($this->product->id, $this->user->id, 'sortie', 30);
    expect((float) $this->product->fresh()->quantite)->toBe(70.0);
});

test('FT-03: ajustement sets absolute quantity', function () {
    $this->service->createMovement($this->product->id, $this->user->id, 'ajustement', 42);
    expect((float) $this->product->fresh()->quantite)->toBe(42.0);
});

test('FT-04: sortie beyond available stock throws validation error', function () {
    expect(fn () => $this->service->createMovement($this->product->id, $this->user->id, 'sortie', 999))
        ->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('FT-05: movement stores quantite_avant and quantite_apres correctly', function () {
    $this->service->createMovement($this->product->id, $this->user->id, 'entree', 25);

    $movement = StockMovement::withoutGlobalScopes()->latest()->first();
    expect((float) $movement->quantite_avant)->toBe(100.0)
        ->and((float) $movement->quantite_apres)->toBe(125.0);
});

test('FT-06: product status changes to alerte when below threshold', function () {
    $this->service->createMovement($this->product->id, $this->user->id, 'sortie', 95);

    $p = $this->product->fresh();
    expect((float) $p->quantite)->toBe(5.0)
        ->and($p->en_alerte)->toBeTrue()
        ->and($p->en_rupture)->toBeFalse();
});
