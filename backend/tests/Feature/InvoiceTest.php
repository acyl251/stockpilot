<?php

uses(\Tests\TestCase::class);

use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->org  = $this->createOrg('Boutique Facture');
    $this->org->update(['matricule_fiscal' => '1234567A', 'adresse' => 'Sousse, Tunisie']);
    $this->user = $this->actingAsOrg($this->org);

    $cat = Category::create(['organisation_id' => $this->org->id, 'nom' => 'Divers', 'couleur' => '#000']);
    $this->product = Product::create([
        'organisation_id' => $this->org->id, 'category_id' => $cat->id,
        'nom' => 'Article A', 'reference' => 'A-001', 'quantite' => 50, 'seuil_alerte' => 2,
        'unite_mesure' => 'unité', 'prix_achat_ht' => 10, 'taux_tva' => 19, 'prix_vente_ht' => 100, 'actif' => true,
    ]);

    $this->makeSale = function (): Sale {
        $res = $this->withJwt($this->user)->postJson('/api/sales', [
            'items' => [['product_id' => $this->product->id, 'quantite' => 2]],
            'mode_paiement' => 'especes', 'montant_paye' => 250,
        ]);
        return Sale::find($res->json('id'));
    };
});

test('la facture PDF se génère et attribue un numéro séquentiel', function () {
    $sale = ($this->makeSale)();
    expect($sale->numero_facture)->toBeNull();

    $res = $this->withJwt($this->user)->get("/api/sales/{$sale->id}/invoice");

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');

    $sale->refresh();
    expect($sale->numero_facture)->toBe('FAC-' . now()->format('Y') . '-0001');
});

test('le numéro de facture est stable et séquentiel entre ventes', function () {
    $s1 = ($this->makeSale)();
    $s2 = ($this->makeSale)();

    $this->withJwt($this->user)->get("/api/sales/{$s1->id}/invoice")->assertOk();
    $this->withJwt($this->user)->get("/api/sales/{$s2->id}/invoice")->assertOk();
    $this->withJwt($this->user)->get("/api/sales/{$s1->id}/invoice")->assertOk(); // re-télécharge

    expect($s1->fresh()->numero_facture)->toBe('FAC-' . now()->format('Y') . '-0001')
        ->and($s2->fresh()->numero_facture)->toBe('FAC-' . now()->format('Y') . '-0002');
});

test('un non-admin ne peut pas modifier les infos de facturation', function () {
    $operateur = User::create([
        'organisation_id' => $this->org->id, 'nom' => 'Op', 'prenom' => 'Erateur',
        'email' => 'operateur@test.tn', 'password' => Hash::make('Password123!'),
        'role' => 'operateur', 'actif' => true,
    ]);

    $this->withJwt($operateur)->patchJson('/api/organisation', ['matricule_fiscal' => 'X'])
        ->assertStatus(403);
});

test('un admin met à jour les infos de facturation', function () {
    $this->withJwt($this->user)->patchJson('/api/organisation', [
        'matricule_fiscal' => '9999999B', 'adresse' => 'Tunis',
    ])->assertOk()->assertJsonPath('matricule_fiscal', '9999999B');
});
