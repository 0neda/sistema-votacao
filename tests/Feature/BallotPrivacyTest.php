<?php

use App\Actions\Voting;
use App\Models\AuditLog;
use App\Models\Participation;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'administrador']);
    auth()->setUser($this->admin);
    $this->election = app(Voting::class)->createElection($this->admin, electionInput());
    $this->round = $this->election->rounds()->firstOrFail();
});

test('each active role can vote once without a stored identity to ballot mapping', function (string $role): void {
    $user = User::factory()->create(['role' => $role]);
    $candidate = $this->round->candidates()->firstOrFail();
    $this->actingAs($user)->post(route('votes.store', $this->round), ['candidate_id' => $candidate->id, 'confirmed' => true])->assertSessionHasNoErrors();
    $this->post(route('votes.store', $this->round), ['candidate_id' => $candidate->id, 'confirmed' => true])->assertSessionHasErrors('vote');
    $this->assertDatabaseCount('votes', 1);
    $this->assertDatabaseCount('participations', 1);
    $this->assertDatabaseHas('participations', ['round_id' => $this->round->id, 'user_id' => $user->id]);
    $vote = Vote::firstOrFail();
    expect($vote->id)->toMatch('/^[a-f0-9]{64}$/');
    expect($vote->candidate_id)->toBe($candidate->id);
    expect(Schema::getColumnListing('votes'))->not->toContain('user_id', 'created_at', 'ip_address', 'participation_id');
    expect(Schema::getColumnListing('participations'))->not->toContain('candidate_id', 'vote_id', 'hash', 'created_at');
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('ownVote', ['round_id' => $this->round->id])->missing('snapshot.election.rounds.0.ballots'));
    expect(AuditLog::where('event', 'VOTO_ACEITO')->firstOrFail()->metadata)->toBe([]);
})->with(['eleitor', 'administrador', 'auditor']);

test('a rejected choice does not consume participation or leave a ballot', function (): void {
    $this->actingAs($this->admin)->post(route('votes.store', $this->round), ['candidate_id' => 999999, 'confirmed' => true])->assertSessionHasErrors('vote');
    $this->assertDatabaseCount('votes', 0);
    $this->assertDatabaseCount('participations', 0);
});

test('participants and anonymous ballots are separately auditable without shared identifiers', function (): void {
    $candidate = $this->round->candidates()->firstOrFail();
    $users = User::factory()->count(2)->create();
    foreach ($users as $user) {
        $this->actingAs($user)->post(route('votes.store', $this->round), ['candidate_id' => $candidate->id, 'confirmed' => true])->assertSessionHasNoErrors();
    }
    expect(Vote::pluck('id')->unique()->count())->toBe(2);
    $this->actingAs($this->admin)->post(route('rounds.close', $this->round))->assertSessionHasNoErrors();
    $this->get(route('audit.index', ['round_id' => $this->round->id]))->assertInertia(fn (Assert $page) => $page
        ->has('participants.data', 2)->has('ballots.data', 2)
        ->where('participants.data.0.name', $users->first()->name)
        ->missing('participants.data.0.hash')->missing('participants.data.0.candidate')
        ->missing('ballots.data.0.user_id')->missing('ballots.data.0.email')->missing('ballots.data.0.created_at'));
});

test('participation is immutable in PostgreSQL', function (string $operation): void {
    Participation::create(['round_id' => $this->round->id, 'user_id' => $this->admin->id]);
    expect(fn () => DB::transaction(function () use ($operation): void {
        $query = DB::table('participations')->where('round_id', $this->round->id);
        if ($operation === 'delete') {
            $query->delete();
        } else {
            $query->update(['round_id' => $this->round->id]);
        }
    }))->toThrow(QueryException::class, 'Registros de votos e auditoria sao imutaveis');
    $this->assertDatabaseCount('participations', 1);
})->with(['update', 'delete']);

test('rejected votes do not persist candidate selection in session input', function (): void {
    $candidate = $this->round->candidates()->firstOrFail();
    $this->actingAs($this->admin)->post(route('votes.store', $this->round), ['candidate_id' => $candidate->id])->assertSessionHasErrors('confirmed');
    expect(session()->getOldInput('candidate_id'))->toBeNull();
    $this->assertDatabaseCount('votes', 0);
    $this->assertDatabaseCount('participations', 0);
});

test('participation protects account history even without a direct user relation on ballots', function (): void {
    $voter = User::factory()->create();
    $candidate = $this->round->candidates()->firstOrFail();
    $this->actingAs($voter)->post(route('votes.store', $this->round), ['candidate_id' => $candidate->id, 'confirmed' => true])->assertSessionHasNoErrors();
    expect($voter->hasVotingHistory())->toBeTrue();
    $this->actingAs($this->admin)->get(route('admin.users'))->assertInertia(fn (Assert $page) => $page->where('users.data.1.can_delete', false));
    $this->delete(route('users.destroy', $voter))->assertSessionHasErrors('user');
    $this->actingAs($voter)->delete(route('profile.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');
    $this->assertModelExists($voter);
    $this->assertDatabaseCount('participations', 1);
    $this->assertDatabaseCount('votes', 1);
});
