<?php

namespace Database\Factories;

use App\Models\Candidate;
use App\Models\Election;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Candidate> */
class CandidateFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['election_id' => Election::factory(), 'name' => fake()->unique()->name(), 'description' => fake()->sentence(), 'display_order' => 1];
    }
}
