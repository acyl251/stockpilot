<?php

uses(\Tests\TestCase::class);

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Services\WhatsAppService;

beforeEach(function () {
    $this->org  = $this->createOrg('Boutique WA');
    $this->org->update(['telephone' => '29123456']);
    $this->user = $this->actingAsOrg($this->org);
});

test('le numéro local tunisien est normalisé en international', function () {
    $wa = new WhatsAppService();
    expect($wa->normalize('29 123 456'))->toBe('21629123456')
        ->and($wa->normalize('+216 29123456'))->toBe('21629123456')
        ->and($wa->normalize('0021629123456'))->toBe('21629123456');
});

test('la relance client renvoie un lien wa.me avec le solde', function () {
    $cat = Category::create(['organisation_id' => $this->org->id, 'nom' => 'D', 'couleur' => '#000']);
    $product = Product::create([
        'organisation_id' => $this->org->id, 'category_id' => $cat->id,
        'nom' => 'Article', 'reference' => 'A1', 'quantite' => 10, 'seuil_alerte' => 1,
        'unite_mesure' => 'u', 'prix_achat_ht' => 50, 'taux_tva' => 0, 'prix_vente_ht' => 100, 'actif' => true,
    ]);
    $client = Client::create(['organisation_id' => $this->org->id, 'nom' => 'Ali', 'telephone' => '29123456']);

    // Vente à crédit → solde 200
    $this->withJwt($this->user)->postJson('/api/sales', [
        'items' => [['product_id' => $product->id, 'quantite' => 2]],
        'mode_paiement' => 'credit', 'client_id' => $client->id,
    ])->assertCreated();

    $res = $this->withJwt($this->user)->postJson("/api/clients/{$client->id}/remind");

    $res->assertOk()
        ->assertJsonPath('to', '21629123456')
        ->assertJsonPath('driver', 'log');
    expect($res->json('wa_link'))->toContain('https://wa.me/21629123456')
        ->and($res->json('message_text'))->toContain('200,000 TND');
});

test('la relance échoue sans téléphone', function () {
    $client = Client::create(['organisation_id' => $this->org->id, 'nom' => 'SansTel']);
    $this->withJwt($this->user)->postJson("/api/clients/{$client->id}/remind")
        ->assertStatus(422);
});

test('l\'alerte stock WhatsApp liste les produits sous le seuil', function () {
    $cat = Category::create(['organisation_id' => $this->org->id, 'nom' => 'D', 'couleur' => '#000']);
    Product::create([
        'organisation_id' => $this->org->id, 'category_id' => $cat->id,
        'nom' => 'Produit Bas', 'reference' => 'B1', 'quantite' => 1, 'seuil_alerte' => 5,
        'unite_mesure' => 'u', 'prix_achat_ht' => 1, 'taux_tva' => 0, 'prix_vente_ht' => 2, 'actif' => true,
    ]);

    $res = $this->withJwt($this->user)->postJson('/api/alerts/notify');

    $res->assertOk()->assertJsonPath('to', '21629123456');
    expect($res->json('message_text'))->toContain('Produit Bas');
});
