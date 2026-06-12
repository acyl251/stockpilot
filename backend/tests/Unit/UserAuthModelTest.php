<?php

uses(\Tests\TestCase::class);

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $plan = Plan::create(['nom' => 'Pro', 'max_utilisateurs' => 10, 'max_produits' => 2000, 'ia_activee' => true, 'prix_mensuel' => 149]);
    $org  = Organisation::create(['plan_id' => $plan->id, 'nom' => 'Org Test', 'email_contact' => 'o@test.tn', 'actif' => true, 'onboarding_complete' => true]);
    $this->org = $org;
});

// UT-AUTH-01: Verrouillage après 5 tentatives échouées
test('UT-AUTH-01: compte verrouille apres 5 tentatives echouees', function () {
    $user = User::create([
        'organisation_id'     => $this->org->id,
        'nom' => 'Test', 'prenom' => 'User',
        'email'               => 'lock@test.tn',
        'password'            => Hash::make('password'),
        'role'                => 'admin',
        'actif'               => true,
        'tentatives_connexion' => 4,
    ]);

    $user->incrementLoginAttempts();

    expect($user->fresh()->tentatives_connexion)->toBe(5)
        ->and($user->fresh()->verrouille_jusqu_a)->not->toBeNull()
        ->and($user->fresh()->isLocked())->toBeTrue();
});

// UT-AUTH-02: Remise à zéro après connexion réussie
test('UT-AUTH-02: compteur remis a zero apres connexion reussie', function () {
    $user = User::create([
        'organisation_id'     => $this->org->id,
        'nom' => 'Test', 'prenom' => 'User',
        'email'               => 'reset@test.tn',
        'password'            => Hash::make('password'),
        'role'                => 'admin',
        'actif'               => true,
        'tentatives_connexion' => 3,
    ]);

    $user->resetLoginAttempts();

    expect($user->fresh()->tentatives_connexion)->toBe(0)
        ->and($user->fresh()->verrouille_jusqu_a)->toBeNull();
});

// UT-AUTH-03: Mot de passe jamais stocké en clair
test('UT-AUTH-03: mot de passe stocke en hash bcrypt jamais en clair', function () {
    $plainPassword = 'MonMotDePasse2026!';

    $user = User::create([
        'organisation_id' => $this->org->id,
        'nom' => 'Test', 'prenom' => 'User',
        'email'           => 'hash@test.tn',
        'password'        => Hash::make($plainPassword),
        'role'            => 'admin',
        'actif'           => true,
    ]);

    $stored = $user->fresh()->password;

    expect($stored)->not->toBe($plainPassword)
        ->and(str_starts_with($stored, '$2y$'))->toBeTrue()
        ->and(Hash::check($plainPassword, $stored))->toBeTrue();
});

// UT-AUTH-04: isLocked retourne false pour un utilisateur non verrouillé
test('UT-AUTH-04: isLocked retourne false pour utilisateur normal', function () {
    $user = User::create([
        'organisation_id'     => $this->org->id,
        'nom' => 'Normal', 'prenom' => 'User',
        'email'               => 'normal@test.tn',
        'password'            => Hash::make('password'),
        'role'                => 'admin',
        'actif'               => true,
        'tentatives_connexion' => 0,
    ]);

    expect($user->isLocked())->toBeFalse();
});

// UT-AUTH-05: 4 tentatives ne verrouillent pas encore
test('UT-AUTH-05: 4 tentatives ne verrouillent pas le compte', function () {
    $user = User::create([
        'organisation_id'     => $this->org->id,
        'nom' => 'Test', 'prenom' => 'User',
        'email'               => 'four@test.tn',
        'password'            => Hash::make('password'),
        'role'                => 'admin',
        'actif'               => true,
        'tentatives_connexion' => 3,
    ]);

    $user->incrementLoginAttempts();

    expect($user->fresh()->tentatives_connexion)->toBe(4)
        ->and($user->fresh()->isLocked())->toBeFalse();
});
