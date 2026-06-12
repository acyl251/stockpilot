<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $prixHT = $this->faker->randomFloat(3, 1, 500);

        return [
            'organisation_id' => Organisation::factory(),
            'nom'             => $this->faker->words(2, true),
            'reference'       => strtoupper($this->faker->unique()->lexify('REF-????')),
            'description'     => $this->faker->sentence(),
            'quantite'        => $this->faker->randomFloat(3, 10, 200),
            'seuil_alerte'    => 10.000,
            'unite_mesure'    => 'unité',
            'prix_achat_ht'   => $prixHT,
            'taux_tva'        => 19.00,
            'prix_vente_ht'   => round($prixHT * 1.3, 3),
            'actif'           => true,
        ];
    }

    public function lowStock(): static
    {
        return $this->state(['quantite' => 5.000, 'seuil_alerte' => 10.000]);
    }

    public function outOfStock(): static
    {
        return $this->state(['quantite' => 0.000, 'seuil_alerte' => 10.000]);
    }

    public function withTva(float $tva): static
    {
        return $this->state(['taux_tva' => $tva]);
    }
}
