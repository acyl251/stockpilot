<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganisationFactory extends Factory
{
    protected $model = Organisation::class;

    public function definition(): array
    {
        return [
            'plan_id'             => Plan::factory(),
            'nom'                 => $this->faker->company(),
            'secteur'             => $this->faker->randomElement(['Commerce', 'Pharmacie', 'Électronique', 'Alimentation']),
            'email_contact'       => $this->faker->unique()->companyEmail(),
            'actif'               => true,
            'onboarding_complete' => true,
        ];
    }

    public function needsOnboarding(): static
    {
        return $this->state(['onboarding_complete' => false]);
    }
}
