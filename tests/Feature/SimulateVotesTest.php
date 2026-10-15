<?php

use App\Actions\Voting;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\User;
use App\Models\Vote;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'administrador']);
    auth()->setUser($this->admin);
    $this->election = app(Voting::class)->createElection($this->admin, electionInput());
    $this->round = $this->election->rounds()->firstOrFail();
    $this->candidate = $this->round->candidates()->firstOrFail();
});

test('simulation lists candidates without creating votes', function (): void {
    $this->artisan('votacao:simular')->expectsOutputToContain($this->candidate->name)->assertSuccessful();
    $this->assertDatabaseCount('votes', 0);
});

test('simulation marks its ballots and scores without consuming real users participation', function (): void {
    $usersBefore = User::count();
    $this->artisan('votacao:simular', ['candidato' => $this->candidate->id, 'quantidade' => 5])->assertSuccessful();
    $this->assertDatabaseCount('votes', 5);
    $this->assertDatabaseCount('participations', 0);
    expect(User::count())->toBe($usersBefore);
    expect(Vote::where('is_simulated', true)->count())->toBe(5);
    expect(Vote::pluck('id')->unique()->count())->toBe(5);
    $log = AuditLog::where('event', 'SIMULACAO_VOTOS')->firstOrFail();
    expect($log->user_id)->toBeNull();
    expect($log->metadata)->toBe(['quantity' => 5]);
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('snapshot.election.rounds.0.total', 5)->where('snapshot.election.rounds.0.simulated_total', 5)->where('ownVote', null));
    $this->actingAs($this->admin)->post(route('votes.store', $this->round), ['candidate_id' => $this->candidate->id, 'confirmed' => true])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('votes', 6);
    $this->assertDatabaseCount('participations', 1);
});

test('simulation rejects invalid quantities without writing votes', function (string $quantity): void {
    $this->artisan('votacao:simular', ['candidato' => $this->candidate->id, 'quantidade' => $quantity])->assertFailed();
    $this->assertDatabaseCount('votes', 0);
    $this->assertDatabaseMissing('audit_logs', ['event' => 'SIMULACAO_VOTOS']);
})->with(['0', '-1', '1.5', 'abc', '10001']);

test('simulation rejects candidates outside the round', function (): void {
    $history = Election::factory()->create(['completed_at' => now()]);
    $outside = Candidate::factory()->create(['election_id' => $history->id]);
    $this->artisan('votacao:simular', ['candidato' => $outside->id])->assertFailed();
    $this->assertDatabaseCount('votes', 0);
});

test('simulation cannot change a closed or scheduled round', function (string $status): void {
    $this->round->update(['status' => $status]);
    $this->artisan('votacao:simular', ['candidato' => $this->candidate->id, '--turno' => $this->round->id])->assertFailed();
    $this->assertDatabaseCount('votes', 0);
})->with(['encerrado', 'agendado']);

test('simulation is unavailable in production', function (): void {
    app()->instance('env', 'production');
    try {
        $this->artisan('votacao:simular', ['candidato' => $this->candidate->id])->assertFailed();
        $this->assertDatabaseCount('votes', 0);
    } finally {
        app()->instance('env', 'testing');
    }
});
