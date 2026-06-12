<?php

uses(\Tests\TestCase::class);

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * MIT-01 to MIT-07: Multi-tenant isolation tests.
 * Each test verifies that tenant A cannot access tenant B's data
 * through any code path (JWT, Global Scope, FK constraints).
 */
beforeEach(function () {
    $plan = Plan::create([
        'nom' => 'Pro', 'max_utilisateurs' => 10,
        'max_produits' => 2000, 'ia_activee' => true, 'prix_mensuel' => 149,
    ]);

    $this->orgA = Organisation::create([
        'plan_id' => $plan->id, 'nom' => 'Org A',
        'email_contact' => 'a@test.tn', 'actif' => true, 'onboarding_complete' => true,
    ]);

    $this->orgB = Organisation::create([
        'plan_id' => $plan->id, 'nom' => 'Org B',
        'email_contact' => 'b@test.tn', 'actif' => true, 'onboarding_complete' => true,
    ]);

    $this->userA = User::create([
        'organisation_id' => $this->orgA->id,
        'nom' => 'Alpha', 'prenom' => 'User',
        'email' => 'user@orga.tn',
        'password' => Hash::make('password'),
        'role' => 'admin', 'actif' => true,
    ]);

    $this->userB = User::create([
        'organisation_id' => $this->orgB->id,
        'nom' => 'Beta', 'prenom' => 'User',
        'email' => 'user@orgb.tn',
        'password' => Hash::make('password'),
        'role' => 'admin', 'actif' => true,
    ]);

    // Products for each org (bypass Global Scope for seeding)
    app()->instance('current_organisation_id', $this->orgA->id);
    $this->productA = Product::create([
        'organisation_id' => $this->orgA->id,
        'nom' => 'Produit A', 'reference' => 'PA-001',
        'seuil_alerte' => 5, 'prix_achat_ht' => 10, 'prix_vente_ht' => 15,
    ]);

    app()->instance('current_organisation_id', $this->orgB->id);
    $this->productB = Product::create([
        'organisation_id' => $this->orgB->id,
        'nom' => 'Produit B', 'reference' => 'PB-001',
        'seuil_alerte' => 5, 'prix_achat_ht' => 10, 'prix_vente_ht' => 15,
    ]);
});

/**
 * MIT-01: Login only returns token for the user's own organisation.
 */
test('MIT-01: login returns JWT with correct organisation_id', function () {
    $response = $this->postJson('/api/auth/login', [
        'email' => 'user@orga.tn', 'password' => 'password',
    ]);

    $response->assertOk()->assertJsonPath('user.organisation_id', $this->orgA->id);
});

/**
 * MIT-02: Global Scope filters products by authenticated organisation.
 */
test('MIT-02: product list shows only current tenant products', function () {
    app()->instance('current_organisation_id', $this->orgA->id);
    app()->instance('current_user', $this->userA);

    $response = $this->withJwt($this->userA)->getJson('/api/products');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($this->productA->id)
        ->and($ids)->not->toContain($this->productB->id);
});

/**
 * MIT-03: Cannot read another tenant's product by direct ID.
 */
test('MIT-03: cannot access cross-tenant product by ID', function () {
    app()->instance('current_organisation_id', $this->orgA->id);
    app()->instance('current_user', $this->userA);

    $this->withJwt($this->userA)
        ->getJson("/api/products/{$this->productB->id}")
        ->assertNotFound();
});

/**
 * MIT-04: Cannot update another tenant's product.
 */
test('MIT-04: cannot update cross-tenant product', function () {
    app()->instance('current_organisation_id', $this->orgA->id);
    app()->instance('current_user', $this->userA);

    $this->withJwt($this->userA)
        ->patchJson("/api/products/{$this->productB->id}", ['nom' => 'Hacked'])
        ->assertNotFound();
});

/**
 * MIT-05: Cannot delete another tenant's product.
 */
test('MIT-05: cannot delete cross-tenant product', function () {
    app()->instance('current_organisation_id', $this->orgA->id);
    app()->instance('current_user', $this->userA);

    $this->withJwt($this->userA)
        ->deleteJson("/api/products/{$this->productB->id}")
        ->assertNotFound();
});

/**
 * MIT-06: Stock movements are scoped to the current tenant.
 */
test('MIT-06: stock movements only returns current tenant movements', function () {
    app()->instance('current_organisation_id', $this->orgA->id);
    app()->instance('current_user', $this->userA);

    $response = $this->withJwt($this->userA)->getJson('/api/movements');

    $response->assertOk();
    // All movements in result must belong to orgA
    collect($response->json('data'))->each(function ($m) {
        expect($m['product']['id'])->toBe($this->productA->id);
    });
});

/**
 * MIT-07: Creating a product always assigns the JWT organisation_id, not a user-supplied one.
 */
test('MIT-07: creating product ignores user-supplied organisation_id', function () {
    app()->instance('current_organisation_id', $this->orgA->id);
    app()->instance('current_user', $this->userA);

    $response = $this->withJwt($this->userA)->postJson('/api/products', [
        'organisation_id' => $this->orgB->id,  // attempt injection
        'nom'             => 'Injected Product',
        'seuil_alerte'    => 5,
        'prix_achat_ht'   => 10,
        'prix_vente_ht'   => 15,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('products', [
        'nom'            => 'Injected Product',
        'organisation_id' => $this->orgA->id,  // must be orgA, not orgB
    ]);
});

