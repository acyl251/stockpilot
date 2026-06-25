<?php

uses(\Tests\TestCase::class);

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;

beforeEach(function () {
    $this->org  = $this->createOrg('Boutique Crédit');
    $this->user = $this->actingAsOrg($this->org);

    $cat = Category::create(['organisation_id' => $this->org->id, 'nom' => 'Divers', 'couleur' => '#000']);

    $this->product = Product::create([
        'organisation_id' => $this->org->id,
        'category_id'     => $cat->id,
        'nom'             => 'Article A',
        'reference'       => 'A-001',
        'quantite'        => 20,
        'seuil_alerte'    => 2,
        'unite_mesure'    => 'unité',
        'prix_achat_ht'   => 10,
        'taux_tva'        => 0,
        'prix_vente_ht'   => 100,
        'actif'           => true,
    ]);
});

test('une vente à crédit crée une créance client et décrémente le stock', function () {
    $res = $this->withJwt($this->user)->postJson('/api/sales', [
        'items'         => [['product_id' => $this->product->id, 'quantite' => 2]],
        'mode_paiement' => 'credit',
        'client_nom'    => 'Mohamed',
    ]);

    $res->assertCreated();
    $sale = Sale::first();

    expect((float) $sale->total_ttc)->toBe(200.0)
        ->and((float) $sale->montant_regle)->toBe(0.0)
        ->and((float) $sale->reste_a_payer)->toBe(200.0)
        ->and($sale->statut_paiement)->toBe('impaye')
        ->and($this->product->fresh()->quantite)->toEqual('18.000');

    $client = Client::where('nom', 'Mohamed')->first();
    expect($client)->not->toBeNull();
});

test('un nouvel achat à crédit s\'ajoute à l\'ancien solde du client', function () {
    $client = Client::create(['organisation_id' => $this->org->id, 'nom' => 'Ali']);

    $this->withJwt($this->user)->postJson('/api/sales', [
        'items' => [['product_id' => $this->product->id, 'quantite' => 1]],
        'mode_paiement' => 'credit', 'client_id' => $client->id,
    ])->assertCreated();

    $this->withJwt($this->user)->postJson('/api/sales', [
        'items' => [['product_id' => $this->product->id, 'quantite' => 3]],
        'mode_paiement' => 'credit', 'client_id' => $client->id,
    ])->assertCreated();

    // Solde = 100 + 300 = 400
    $res = $this->withJwt($this->user)->getJson("/api/clients/{$client->id}");
    $res->assertOk()->assertJsonPath('solde', fn($v) => (float)$v === 400.0);
});

test('un paiement réduit le solde en imputant les plus anciennes ventes', function () {
    $client = Client::create(['organisation_id' => $this->org->id, 'nom' => 'Sami']);

    $this->withJwt($this->user)->postJson('/api/sales', [
        'items' => [['product_id' => $this->product->id, 'quantite' => 2]], // 200
        'mode_paiement' => 'credit', 'client_id' => $client->id,
    ]);
    $this->withJwt($this->user)->postJson('/api/sales', [
        'items' => [['product_id' => $this->product->id, 'quantite' => 1]], // 100
        'mode_paiement' => 'credit', 'client_id' => $client->id,
    ]);

    // Paie 250 → solde 300 - 250 = 50
    $this->withJwt($this->user)->postJson("/api/clients/{$client->id}/pay", ['montant' => 250])
        ->assertCreated()
        ->assertJsonPath('solde', fn($v) => (float)$v === 50.0);

    // La 1re vente (200) doit être soldée, la 2e partiellement (50/100)
    $sales = Sale::where('client_id', $client->id)->orderBy('id')->get();
    expect((float) $sales[0]->reste_a_payer)->toBe(0.0)
        ->and((float) $sales[1]->reste_a_payer)->toBe(50.0);
});

test('on ne peut pas encaisser plus que le solde dû', function () {
    $client = Client::create(['organisation_id' => $this->org->id, 'nom' => 'Nora']);
    $this->withJwt($this->user)->postJson('/api/sales', [
        'items' => [['product_id' => $this->product->id, 'quantite' => 1]], // 100
        'mode_paiement' => 'credit', 'client_id' => $client->id,
    ]);

    // Paie 500 mais ne doit imputer que 100
    $this->withJwt($this->user)->postJson("/api/clients/{$client->id}/pay", ['montant' => 500])
        ->assertCreated()
        ->assertJsonPath('solde', fn($v) => (float)$v === 0.0)
        ->assertJsonPath('payment.montant', '100.000');
});
