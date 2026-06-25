<?php

uses(\Tests\TestCase::class);

use App\Models\User;
use App\Services\VerificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->org = $this->createOrg('Boutique Verif');
});

function makeUser($org, array $extra = []): User
{
    return User::create(array_merge([
        'organisation_id' => $org->id,
        'nom' => 'Test', 'prenom' => 'User',
        'email' => 'verif@test.tn',
        'password' => Hash::make('Password123!'),
        'role' => 'admin', 'actif' => true,
    ], $extra));
}

test('un utilisateur avec un code en attente ne peut pas se connecter', function () {
    makeUser($this->org, [
        'email_verified_at' => null,
        'verification_code' => '123456',
        'verification_code_expires_at' => now()->addMinutes(30),
    ]);

    $this->postJson('/api/auth/login', ['email' => 'verif@test.tn', 'password' => 'Password123!'])
        ->assertStatus(403)
        ->assertJsonPath('verification_required', true)
        ->assertJsonPath('email', 'verif@test.tn');
});

test('un utilisateur déjà vérifié (sans code) se connecte normalement', function () {
    makeUser($this->org, ['verification_code' => null]);

    $this->postJson('/api/auth/login', ['email' => 'verif@test.tn', 'password' => 'Password123!'])
        ->assertOk()
        ->assertJsonStructure(['access_token', 'user']);
});

test('le bon code vérifie l\'email et connecte', function () {
    makeUser($this->org, [
        'email_verified_at' => null,
        'verification_code' => '654321',
        'verification_code_expires_at' => now()->addMinutes(30),
    ]);

    $this->postJson('/api/auth/verify-email', ['email' => 'verif@test.tn', 'code' => '654321'])
        ->assertOk()
        ->assertJsonStructure(['access_token', 'user']);

    $user = User::where('email', 'verif@test.tn')->first();
    expect($user->verification_code)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull();
});

test('un mauvais code est refusé', function () {
    makeUser($this->org, [
        'email_verified_at' => null,
        'verification_code' => '111111',
        'verification_code_expires_at' => now()->addMinutes(30),
    ]);

    $this->postJson('/api/auth/verify-email', ['email' => 'verif@test.tn', 'code' => '000000'])
        ->assertStatus(422);
});

test('un code expiré est refusé', function () {
    makeUser($this->org, [
        'email_verified_at' => null,
        'verification_code' => '222222',
        'verification_code_expires_at' => now()->subMinute(),
    ]);

    $this->postJson('/api/auth/verify-email', ['email' => 'verif@test.tn', 'code' => '222222'])
        ->assertStatus(422);
});

test('le service envoie un email et stocke un code à 6 chiffres', function () {
    $user = makeUser($this->org, ['verification_code' => null]);

    $code = app(VerificationService::class)->issue($user->fresh());

    expect($code)->toMatch('/^\d{6}$/');
    expect($user->fresh()->verification_code)->toBe($code);
    Mail::assertSent(\App\Mail\VerificationCodeMail::class);
});
