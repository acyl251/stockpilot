<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotent: skip if the demo admin already exists (safe to run on every deploy)
        if (User::where('email', 'admin@test.tn')->exists()) {
            $this->command?->info('Database already seeded — skipping.');
            return;
        }

        $plan = Plan::where('nom', 'Pro')->first();

        $org = Organisation::create([
            'plan_id'              => $plan->id,
            'nom'                  => 'Entreprise Test SARL',
            'secteur'              => 'restauration',
            'email_contact'        => 'contact@test.tn',
            'telephone'            => '+216 71 000 000',
            'onboarding_complete'  => true,
            'actif'                => true,
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

        $this->command->info("Seeded: org={$org->nom}, user={$admin->email} (password: Password123!)");
    }
}
