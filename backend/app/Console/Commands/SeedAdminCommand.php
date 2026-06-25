<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent admin/demo seeding for production (Railway).
 *
 * Replaces `db:seed`, which hangs without a TTY even with --force.
 * A plain console command has no confirmation prompt, so it never blocks.
 */
class SeedAdminCommand extends Command
{
    protected $signature = 'app:seed-admin';

    protected $description = 'Create the demo admin user and sample data (idempotent)';

    public function handle(): int
    {
        $this->seedSuperAdmin();

        if (User::where('email', 'admin@test.tn')->exists()) {
            $this->info('Demo admin already exists — skipping demo data.');
            return self::SUCCESS;
        }

        $plan = Plan::where('nom', 'Pro')->first();
        if (! $plan) {
            $this->error('Plan "Pro" introuvable — lancez les migrations d\'abord.');
            return self::FAILURE;
        }

        $org = Organisation::create([
            'plan_id'             => $plan->id,
            'nom'                 => 'Entreprise Test SARL',
            'secteur'             => 'Commerce de détail',
            'email_contact'       => 'contact@test.tn',
            'telephone'           => '+216 71 000 000',
            'onboarding_complete' => true,
            'actif'               => true,
        ]);

        $admin = User::create([
            'organisation_id' => $org->id,
            'nom'             => 'Ben Ali',
            'prenom'          => 'Mohamed',
            'email'           => 'admin@test.tn',
            'password'        => Hash::make('Password123!'),
            'role'            => 'admin',
            'actif'           => true,
        ]);

        // Bind org for tenant scope during seeding
        app()->instance('current_organisation_id', $org->id);

        $cat = Category::create([
            'organisation_id' => $org->id,
            'nom'             => 'Électronique',
            'couleur'         => '#3B82F6',
        ]);

        $type = ProductType::create([
            'organisation_id' => $org->id,
            'nom'             => 'Matériel informatique',
            'icone'           => '💻',
        ]);

        Product::create([
            'organisation_id' => $org->id,
            'category_id'     => $cat->id,
            'product_type_id' => $type->id,
            'nom'             => 'Ordinateur portable Dell XPS 15',
            'reference'       => 'DELL-XPS-001',
            'quantite'        => 10,
            'seuil_alerte'    => 3,
            'unite_mesure'    => 'unité',
            'prix_achat_ht'   => 1200.00,
            'taux_tva'        => 19,
            'prix_vente_ht'   => 1600.00,
            'actif'           => true,
        ]);

        Product::create([
            'organisation_id' => $org->id,
            'category_id'     => $cat->id,
            'nom'             => 'Souris Logitech MX Master 3',
            'reference'       => 'LOG-MX3-001',
            'quantite'        => 2,
            'seuil_alerte'    => 5,
            'unite_mesure'    => 'unité',
            'prix_achat_ht'   => 80.00,
            'taux_tva'        => 19,
            'prix_vente_ht'   => 120.00,
            'actif'           => true,
        ]);

        $this->info("Created demo admin {$admin->email} (org={$org->nom}, password: Password123!)");

        return self::SUCCESS;
    }

    /**
     * Create the platform super-admin (no organisation). Idempotent.
     * Credentials come from env, with sensible defaults.
     */
    private function seedSuperAdmin(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', 'admin@stockpilot.tn');

        // Only one super-admin is allowed on the whole platform.
        if (User::where('role', 'super_admin')->exists()) {
            $this->info('A super-admin already exists — skipping (only one allowed).');
            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->info("Email {$email} already used — skipping super-admin creation.");
            return;
        }

        $password = env('SUPER_ADMIN_PASSWORD', 'SuperAdmin@2025');

        User::create([
            'organisation_id' => null,
            'nom'             => 'Super',
            'prenom'          => 'Admin',
            'email'           => $email,
            'password'        => Hash::make($password),
            'role'            => 'super_admin',
            'actif'           => true,
        ]);

        $this->info("Created super-admin {$email}");
    }
}
