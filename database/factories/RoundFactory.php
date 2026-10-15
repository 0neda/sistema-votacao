<?php

namespace Database\Factories;

use App\Models\Election;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Round> */
class RoundFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['election_id' => Election::factory(), 'number' => 1, 'status' => 'agendado', 'scheduled_at' => now()->addHour()];
    }
}
