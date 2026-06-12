<?php

namespace Tests;

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';

        // Boot the application so DB resolver and facades are available
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Authenticate as a user belonging to the given organisation
     * and bind the tenant context into the container.
     */
    protected function actingAsOrg(Organisation $org, string $role = 'admin'): User
    {
        $user = User::create([
            'organisation_id'    => $org->id,
            'nom'                => 'Test',
            'prenom'             => 'User',
            'email'              => "user_{$org->id}@test.tn",
            'password'           => Hash::make('Password123!'),
            'role'               => $role,
            'actif'              => true,
            'tentatives_connexion' => 0,
        ]);

        app()->instance('current_organisation_id', $org->id);
        app()->instance('current_user', $user);

        $this->actingAs($user);

        return $user;
    }

    /**
     * Make HTTP requests authenticated with a real JWT token (bypasses actingAs which doesn't set JWT).
     */
    protected function withJwt(User $user): static
    {
        $token = JWTAuth::fromUser($user);
        return $this->withToken($token);
    }

    /**
     * Create a plan + organisation pair for tests.
     */
    protected function createOrg(string $nom = 'Test Org', bool $ia = true): Organisation
    {
        $plan = Plan::firstOrCreate(
            ['nom' => 'Pro'],
            ['max_utilisateurs' => 10, 'max_produits' => 2000, 'ia_activee' => $ia, 'prix_mensuel' => 149, 'actif' => true]
        );

        return Organisation::create([
            'plan_id'             => $plan->id,
            'nom'                 => $nom,
            'email_contact'       => strtolower(str_replace(' ', '', $nom)) . '@test.tn',
            'actif'               => true,
            'onboarding_complete' => true,
        ]);
    }
}
