<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'organisation_id'     => Organisation::factory(),
            'nom'                 => $this->faker->lastName(),
            'prenom'              => $this->faker->firstName(),
            'email'               => $this->faker->unique()->safeEmail(),
            'password'            => Hash::make('Password123!'),
            'role'                => 'admin',
            'actif'               => true,
            'tentatives_connexion' => 0,
            'verrouille_jusqu_a'  => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    public function operateur(): static
    {
        return $this->state(['role' => 'operateur']);
    }

    public function locked(): static
    {
        return $this->state([
            'tentatives_connexion' => 5,
            'verrouille_jusqu_a'   => now()->addMinutes(15),
        ]);
    }
}
