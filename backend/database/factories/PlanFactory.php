<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'nom'              => $this->faker->randomElement(['Starter', 'Pro', 'Enterprise']),
            'max_utilisateurs' => 10,
            'max_produits'     => 2000,
            'ia_activee'       => true,
            'prix_mensuel'     => 149.000,
            'actif'            => true,
        ];
    }

    public function withoutAI(): static
    {
        return $this->state(['ia_activee' => false]);
    }
}
