<?php

use App\Actions\Voting;
use App\Models\AuditLog;
use App\Models\Participation;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class, DatabaseMigrations::class);

test('independent processes serialize competing votes and closing', function (bool $close) {
    expect(DB::connection()->getDatabaseName())->toBe('votacao_test');
    $admin = User::factory()->create(['role' => 'administrador']);
    $elector = User::factory()->create();
    auth()->setUser($admin);
    $election = app(Voting::class)->createElection($admin, electionInput());
    $round = $election->rounds()->firstOrFail();
    $candidate = $round->candidates()->firstOrFail();
    $workers = [];
    DB::select('SELECT pg_advisory_lock(731030)');
    try {
        foreach (['vote', $close ? 'close' : 'vote'] as $operation) {
            $worker = new Process([PHP_BINARY, base_path('tests/Fixtures/concurrent-voting-worker.php'), $operation, (string) ($operation === 'close' ? $admin->id : $elector->id), (string) $round->id, (string) $candidate->id], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'votacao_test']);
            $worker->setTimeout(15);
            $worker->start();
            $workers[] = $worker;
        }
        foreach ($workers as $worker) {
            $ready = $worker->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready'));
            expect($ready)->toBeTrue($worker->getErrorOutput());
        }
    } finally {
        DB::select('SELECT pg_advisory_unlock(731030)');
    }
    try {
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput());
        }
        $votes = Vote::where('round_id', $round->id)->count();
        expect($votes)->toBeLessThanOrEqual(1);
        expect(Participation::where('round_id', $round->id)->count())->toBe($votes);
        expect(AuditLog::where('event', 'VOTO_ACEITO')->count())->toBe($votes);
        if ($close) {
            expect($round->fresh()->status)->toBe('encerrado');
            expect($election->fresh()->completed_at)->not->toBeNull();
            expect(AuditLog::where('event', 'ENCERRAMENTO_TURNO')->count())->toBe(1);
        } else {
            expect($votes)->toBe(1);
            expect(AuditLog::where('event', 'VOTO_DUPLICADO')->count())->toBe(1);
        }
    } finally {
        foreach ($workers as $worker) {
            $worker->stop();
        }
    }
})->with(['duplicate vote' => false, 'vote versus close' => true]);
