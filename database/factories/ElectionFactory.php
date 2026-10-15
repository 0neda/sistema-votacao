<?php

namespace Database\Factories;

use App\Models\Election;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Election> */
class ElectionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'created_by' => User::factory()->state(['role' => 'administrador'])];
    }
}
