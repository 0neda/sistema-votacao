<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

beforeEach(function () {
    config(['inertia.ssr.enabled' => false]);
})->in('Feature');

function electionInput(array $overrides = []): array
{
    return array_replace([
        'title' => 'Representante da turma',
        'opening' => 'imediata', 'scheduled_at' => null,
        'candidates' => [
            ['name' => 'Ana'],
            ['name' => 'Bruno'],
            ['name' => 'Carla'],
        ],
    ], $overrides);
}
