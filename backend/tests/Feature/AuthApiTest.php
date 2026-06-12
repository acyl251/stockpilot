<?php

uses(\Tests\TestCase::class);

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $plan = Plan::create(['nom' => 'Pro', 'max_utilisateurs' => 10, 'max_produits' => 2000, 'ia_activee' => true, 'prix_mensuel' => 149]);
    $this->org = Organisation::create(['plan_id' => $plan->id, 'nom' => 'Test Org', 'email_contact' => 'org@test.tn', 'actif' => true, 'onboarding_complete' => true]);
    $this->user = User::create([
        'organisation_id'     => $this->org->id,
        'nom'                 => 'Admin',
        'prenom'              => 'Test',
        'email'               => 'admin@test.tn',
        'password'            => Hash::make('Password123!'),
        'role'                => 'admin',
        'actif'               => true,
        'tentatives_connexion' => 0,
    ]);
});

// IT-AUTH-01: Connexion rÃ©ussie retourne un JWT
test('IT-AUTH-01: connexion reussie retourne token JWT et info utilisateur', function () {
    $response = $this->postJson('/api/auth/login', [
        'email'    => 'admin@test.tn',
        'password' => 'Password123!',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'user'])
        ->assertJsonPath('user.email', 'admin@test.tn')
        ->assertJsonPath('user.organisation_id', $this->org->id);
});

// IT-AUTH-02: Mauvais mot de passe â†' 401
test('IT-AUTH-02: mauvais mot de passe retourne 401', function () {
    $response = $this->postJson('/api/auth/login', [
        'email'    => 'admin@test.tn',
        'password' => 'WrongPassword!',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Identifiants incorrects.');
});

// IT-AUTH-03: Email inexistant â†' 401
test('IT-AUTH-03: email inexistant retourne 401', function () {
    $response = $this->postJson('/api/auth/login', [
        'email'    => 'nobody@nowhere.tn',
        'password' => 'Password123!',
    ]);

    $response->assertUnauthorized();
});

// IT-AUTH-04: Compte verrouillÃ© â†' 423
test('IT-AUTH-04: compte verrouille retourne 423', function () {
    $this->user->update([
        'tentatives_connexion' => 5,
        'verrouille_jusqu_a'   => now()->addMinutes(15),
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => 'admin@test.tn',
        'password' => 'Password123!',
    ]);

    $response->assertStatus(423);
});

// IT-AUTH-05: Compte dÃ©sactivÃ© â†' 403
test('IT-AUTH-05: compte desactive retourne 403', function () {
    $this->user->update(['actif' => false]);

    $response = $this->postJson('/api/auth/login', [
        'email'    => 'admin@test.tn',
        'password' => 'Password123!',
    ]);

    $response->assertForbidden();
});

// IT-AUTH-06: Token invalide rejetÃ© par le middleware â†' 401
test('IT-AUTH-06: token JWT invalide est rejete par le middleware', function () {
    $response = $this->getJson('/api/products', [
        'Authorization' => 'Bearer token_invalide_bidon',
    ]);

    $response->assertUnauthorized();
});

// IT-AUTH-07: /me retourne l'utilisateur authentifiÃ©
test('IT-AUTH-07: GET me retourne le profil de l utilisateur authentifie', function () {
    app()->instance('current_organisation_id', $this->org->id);
    app()->instance('current_user', $this->user);

    $response = $this->withJwt($this->user)->getJson('/api/auth/me');

    $response->assertOk()
        ->assertJsonPath('email', 'admin@test.tn');
});

// IT-AUTH-08: Validation des champs manquants â†' 422
test('IT-AUTH-08: champs manquants retournent 422', function () {
    $response = $this->postJson('/api/auth/login', []);
    $response->assertUnprocessable();
});

// IT-AUTH-09: 5 mauvaises tentatives â†' compte verrouillÃ©
test('IT-AUTH-09: 5 tentatives echouees verrouillent le compte', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/auth/login', [
            'email'    => 'admin@test.tn',
            'password' => 'WrongPassword!',
        ]);
    }

    expect($this->user->fresh()->isLocked())->toBeTrue();
});
