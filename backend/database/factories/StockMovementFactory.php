<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'product_id'      => Product::factory(),
            'user_id'         => User::factory(),
            'type_mouvement'  => $this->faker->randomElement(['entree', 'sortie', 'ajustement']),
            'quantite'        => $this->faker->randomFloat(3, 1, 50),
            'quantite_avant'  => 100.000,
            'quantite_apres'  => 120.000,
            'note'            => $this->faker->optional()->sentence(),
            'date_mouvement'  => now(),
        ];
    }
}
