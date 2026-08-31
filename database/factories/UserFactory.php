<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name'   => fake()->firstName(),
            'last_name'    => fake()->lastName(),
            'phone_number' => fake()->unique()->numerify('08##########'),
            'address'      => fake()->address(),
            'pin'          => Hash::make('123456'),
            'balance'      => 0,
        ];
    }
}
