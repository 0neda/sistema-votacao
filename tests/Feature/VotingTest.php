<?php

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Participation;
use App\Models\Round;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('administrator creates an election with three candidates and an open round', function () {
    $admin = User::factory()->create(['role' => 'administrador']);
    $this->actingAs($admin)->post(route('elections.store'), electionInput())->assertRedirect(route('admin.elections'));
    $this->assertDatabaseCount('elections', 1);
    $this->assertDatabaseCount('candidates', 3);
    $this->assertDatabaseHas('elections', ['description' => '']);
    $this->assertDatabaseHas('candidates', ['name' => 'Ana', 'description' => '']);
    $this->assertDatabaseHas('rounds', ['number' => 1, 'status' => 'aberto']);
    $this->assertDatabaseHas('audit_logs', ['event' => 'CRIACAO_ELEICAO']);
});

test('an elector votes once and a duplicate is audited without replacing the vote', function () {
    $admin = User::factory()->create(['role' => 'administrador']);
    $this->actingAs($admin)->post(route('elections.store'), electionInput());
    $round = Round::firstOrFail();
    $candidate = $round->candidates()->firstOrFail();
    $elector = User::factory()->create();
    $this->actingAs($elector)->post(route('votes.store', $round), ['candidate_id' => $candidate->id, 'confirmed' => true])->assertSessionHasNoErrors();
    $this->post(route('votes.store', $round), ['candidate_id' => $candidate->id, 'confirmed' => true])->assertSessionHasErrors('vote');
    $this->assertDatabaseCount('votes', 1);
    $this->assertDatabaseHas('audit_logs', ['event' => 'VOTO_DUPLICADO', 'result' => 'rejeitado']);
});

function openElectionForTest(array $overrides = []): Round
{
    $admin = User::factory()->create(['role' => 'administrador']);
    test()->actingAs($admin)->post(route('elections.store'), electionInput($overrides))->assertSessionHasNoErrors();

    return Round::orderByDesc('id')->firstOrFail();
}

function castVotesForTest(Round $round, array $counts): void
{
    foreach ($round->candidates as $index => $candidate) {
        for ($count = 0; $count < $counts[$index]; $count++) {
            Participation::create(['round_id' => $round->id, 'user_id' => User::factory()->create()->id]);
            Vote::create(['round_id' => $round->id, 'candidate_id' => $candidate->id]);
        }
    }
}

test('only active administrators can create elections and denied access is audited', function (string $role, bool $active) {
    $user = User::factory()->create(['role' => $role, 'is_active' => $active]);
    $this->actingAs($user)->post(route('elections.store'), electionInput())->assertForbidden();
    $this->assertDatabaseCount('elections', 0);
    $this->assertDatabaseHas('audit_logs', ['event' => 'ACESSO_NEGADO', 'user_id' => $user->id]);
})->with([['eleitor', true], ['auditor', true], ['administrador', false], ['eleitor', false]]);

test('visitors can read public scores but must authenticate before voting', function () {
    $round = openElectionForTest();
    $this->actingAsGuest();
    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('welcome')->has('snapshot.election.rounds.0.candidates', 3)->missing('snapshot.election.rounds.0.votes')->where('ownVote', null));
    $this->post(route('votes.store', $round), ['candidate_id' => $round->candidates->first()->id, 'confirmed' => true])->assertRedirect(route('login'));
});

test('scheduled rounds open on demand at the scheduled instant with one system audit event', function () {
    $this->travelTo(now()->startOfMinute());
    $round = openElectionForTest(['opening' => 'agendada', 'scheduled_at' => now()->addHour()->setTimezone('America/Sao_Paulo')->format('Y-m-d\\TH:i')]);
    $this->get(route('home'))->assertOk();
    expect($round->fresh()->status)->toBe('agendado');
    $this->travel(1)->hours();
    $this->get(route('home'))->assertOk();
    $this->get(route('home'))->assertOk();
    expect($round->fresh()->status)->toBe('aberto');
    $this->assertDatabaseCount('votes', 0);
    expect(AuditLog::where('event', 'ABERTURA_AUTOMATICA')->count())->toBe(1);
    $this->assertDatabaseHas('audit_logs', ['event' => 'ABERTURA_AUTOMATICA', 'user_id' => null]);
});

test('a second election is blocked while the first is scheduled open or awaiting a tie break', function () {
    $round = openElectionForTest();
    $this->post(route('elections.store'), electionInput())->assertSessionHasErrors('election');
    castVotesForTest($round, [1, 1, 0]);
    $this->post(route('rounds.close', $round))->assertSessionHasNoErrors();
    $this->post(route('elections.store'), electionInput())->assertSessionHasErrors('election');
    $this->post(route('rounds.store', $round->election_id), ['scheduled_at' => now()->addDay()->toIso8601String()])->assertSessionHasNoErrors();
    $this->post(route('elections.store'), electionInput())->assertSessionHasErrors('election');
    $this->assertDatabaseCount('elections', 1);
});

test('three distinct candidates and a future schedule are required', function (array $override, string $field) {
    $admin = User::factory()->create(['role' => 'administrador']);
    $this->actingAs($admin)->post(route('elections.store'), electionInput($override))->assertSessionHasErrors($field);
    $this->assertDatabaseCount('elections', 0);
    $this->assertDatabaseCount('candidates', 0);
})->with([
    [['candidates' => [['name' => 'Ana']]], 'candidates'],
    [['candidates' => [['name' => 'Ana'], ['name' => ' ana '], ['name' => 'Carla']]], 'candidates.0.name'],
    [['opening' => 'agendada', 'scheduled_at' => '2020-01-01T12:00'], 'scheduled_at'],
]);

test('inactive accounts cannot vote regardless of role', function (string $role, bool $active) {
    $round = openElectionForTest();
    $user = User::factory()->create(['role' => $role, 'is_active' => $active]);
    $this->actingAs($user)->post(route('votes.store', $round), ['candidate_id' => $round->candidates->first()->id, 'confirmed' => true])->assertForbidden();
    $this->assertDatabaseCount('votes', 0);
    $this->assertDatabaseHas('audit_logs', ['event' => 'VOTO_REJEITADO']);
})->with([['administrador', false], ['auditor', false], ['eleitor', false]]);

test('a candidate outside the round is rejected without logging the chosen candidate', function () {
    $round = openElectionForTest();
    $elector = User::factory()->create();
    $this->actingAs($elector)->post(route('votes.store', $round), ['candidate_id' => 999999, 'confirmed' => true])->assertSessionHasErrors('vote');
    $this->assertDatabaseCount('votes', 0);
    $log = AuditLog::where('event', 'VOTO_REJEITADO')->firstOrFail();
    expect($log->metadata)->toBe(['reason' => 'turno_ou_candidato_invalido']);
});

test('voting requires explicit confirmation', function () {
    $round = openElectionForTest();
    $elector = User::factory()->create();
    $this->actingAs($elector)->post(route('votes.store', $round), ['candidate_id' => $round->candidates->first()->id])->assertSessionHasErrors('confirmed');
    $this->assertDatabaseCount('votes', 0);
    $this->assertDatabaseHas('audit_logs', ['event' => 'VOTO_REJEITADO']);
});

test('a closed round rejects votes and can never reopen', function () {
    $round = openElectionForTest();
    $this->post(route('rounds.close', $round))->assertSessionHasNoErrors();
    $elector = User::factory()->create();
    $this->actingAs($elector)->post(route('votes.store', $round), ['candidate_id' => $round->candidates->first()->id, 'confirmed' => true])->assertSessionHasErrors('vote');
    $this->assertDatabaseCount('votes', 0);
    $this->get(route('home'))->assertOk();
    expect($round->fresh()->status)->toBe('encerrado');
});

test('closing calculates the leader tie or failure and only ties permit another round', function (array $counts, string $outcome, bool $completed, int $leaders) {
    $round = openElectionForTest();
    castVotesForTest($round, $counts);
    $this->post(route('rounds.close', $round))->assertSessionHasNoErrors();
    $this->get(route('home'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('snapshot.election.rounds.0.outcome', $outcome)
        ->has('snapshot.election.rounds.0.leaders', $leaders)
        ->where('snapshot.has_active_election', ! $completed));
    $this->post(route('rounds.store', $round->election_id), ['scheduled_at' => now()->addDay()->toIso8601String()])
        ->{$completed ? 'assertSessionHasErrors' : 'assertSessionHasNoErrors'}();
    expect($round->election->fresh()->completed_at !== null)->toBe($completed);
})->with([
    [[3, 1, 1], 'vencedor', true, 1],
    [[2, 2, 1], 'empate', false, 2],
    [[1, 1, 1], 'empate', false, 3],
    [[0, 0, 0], 'falha', true, 0],
]);

test('a failed election allows a new election and remains in history', function () {
    $round = openElectionForTest();
    $this->post(route('rounds.close', $round))->assertSessionHasNoErrors();
    $this->post(route('elections.store'), electionInput(['title' => 'Nova eleição']))->assertSessionHasNoErrors();
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('snapshot.election.title', 'Nova eleição')->where('history.data.0.rounds.0.outcome', 'falha'));
});

test('a third round permits an original candidate excluded from the second and requires justification', function () {
    $round = openElectionForTest();
    castVotesForTest($round, [1, 1, 0]);
    $this->post(route('rounds.close', $round));
    $this->post(route('rounds.store', $round->election_id), ['scheduled_at' => now()->addMinute()->toIso8601String()])->assertSessionHasNoErrors();
    $second = Round::orderByDesc('id')->firstOrFail();
    expect($second->candidates()->pluck('candidates.id')->all())->toBe($round->candidates->take(2)->pluck('id')->all());
    $this->travel(2)->minutes();
    $this->get(route('home'));
    castVotesForTest($second, [1, 1]);
    $this->post(route('rounds.close', $second));
    $ids = [$round->candidates[0]->id, $round->candidates[2]->id];
    $payload = ['scheduled_at' => now()->addHour()->toIso8601String(), 'candidate_ids' => $ids];
    $this->post(route('rounds.store', $round->election_id), $payload)->assertSessionHasErrors('selection_justification');
    $this->post(route('rounds.store', $round->election_id), [...$payload, 'selection_justification' => 'Ampliar a representação.'])->assertSessionHasNoErrors();
    $third = Round::orderByDesc('id')->firstOrFail();
    expect($third->candidates()->pluck('candidates.id')->all())->toBe($ids);
    $this->assertDatabaseHas('audit_logs', ['event' => 'AGENDAMENTO_TURNO', 'target_id' => $third->id]);
    expect(AuditLog::where('event', 'AGENDAMENTO_TURNO')->orderByDesc('id')->first()->metadata['justification'])->toBe('Ampliar a representação.');
});

test('public props reveal only aggregates and the authenticated electors own receipt', function () {
    $round = openElectionForTest();
    $first = User::factory()->create(['name' => 'Eleitor privado']);
    $second = User::factory()->create();
    $this->actingAs($first)->post(route('votes.store', $round), ['candidate_id' => $round->candidates[0]->id, 'confirmed' => true]);
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('ownVote.round_id', $round->id)->missing('ownVote.candidate_id')->missing('ownVote.created_at')->missing('snapshot.election.rounds.0.votes'));
    $this->actingAs($second)->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('ownVote', null));
    $this->actingAsGuest();
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('auth.user', null)->where('ownVote', null)->where('snapshot.election.rounds.0.total', 1))->assertDontSee('Eleitor privado');
});

test('only active auditors and administrators can inspect anonymous ballots after closure', function (string $role) {
    $round = openElectionForTest();
    castVotesForTest($round, [1, 0, 0]);
    $reader = User::factory()->create(['role' => $role]);
    $this->actingAs($reader)->get(route('audit.index', ['round_id' => $round->id]))->assertForbidden();
    $this->actingAs(User::findOrFail($round->election->created_by))->post(route('rounds.close', $round));
    $this->actingAs($reader)->get(route('audit.index', ['round_id' => $round->id]))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->has('ballots.data', 1)->where('ballots.data.0.candidate', $round->candidates[0]->name)->missing('ballots.data.0.elector')->missing('ballots.data.0.email')->has('participants.data', 1));
    $this->assertDatabaseHas('audit_logs', ['event' => 'CONSULTA_CEDULAS', 'user_id' => $reader->id]);
})->with(['administrador', 'auditor']);

test('electors and inactive auditors cannot access audit data', function (string $role, bool $active) {
    $round = openElectionForTest();
    $user = User::factory()->create(['role' => $role, 'is_active' => $active]);
    $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
})->with([['eleitor', true], ['auditor', false], ['administrador', false]]);

test('PostgreSQL triggers reject updates and deletes of votes and audit logs', function (string $table, string $operation) {
    $round = openElectionForTest();
    castVotesForTest($round, [1, 0, 0]);
    expect(DB::getDriverName())->toBe('pgsql');
    expect(fn () => DB::transaction(function () use ($table, $operation) {
        $query = DB::table($table)->where('id', DB::table($table)->min('id'));
        if ($operation === 'delete') {
            $query->delete();
        } else {
            $query->update($table === 'votes' ? ['is_simulated' => true] : ['created_at' => now()]);
        }
    }))->toThrow(QueryException::class, 'Registros de votos e auditoria sao imutaveis');
    $this->assertDatabaseCount('votes', 1);
})->with([['votes', 'update'], ['votes', 'delete'], ['audit_logs', 'update'], ['audit_logs', 'delete']]);

test('the database prevents overlapping elections and duplicate votes', function () {
    $round = openElectionForTest();
    expect(fn () => DB::transaction(fn () => Election::create(['title' => 'Outra', 'description' => 'Outra eleição', 'created_by' => $round->election->created_by])))
        ->toThrow(UniqueConstraintViolationException::class);
    $user = User::factory()->create();
    $vote = ['id' => bin2hex(random_bytes(32)), 'round_id' => $round->id, 'candidate_id' => $round->candidates[0]->id];
    $participation = ['round_id' => $round->id, 'user_id' => $user->id];
    Participation::create($participation);
    expect(fn () => DB::transaction(fn () => Participation::create($participation)))->toThrow(UniqueConstraintViolationException::class);
    Vote::create($vote);
    expect(fn () => DB::transaction(fn () => Vote::create($vote)))->toThrow(UniqueConstraintViolationException::class);
    $this->assertDatabaseCount('elections', 1);
    $this->assertDatabaseCount('votes', 1);
});

test('registration ignores submitted role and active status', function () {
    $this->post(route('register'), ['name' => 'Novo eleitor', 'email' => 'novo@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'role' => 'administrador', 'is_active' => false])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('users', ['email' => 'novo@example.com', 'role' => 'eleitor', 'is_active' => true]);
});

test('impossible scheduled calendar dates are not silently normalized', function () {
    $admin = User::factory()->create(['role' => 'administrador']);
    $this->actingAs($admin)->post(route('elections.store'), electionInput(['opening' => 'agendada', 'scheduled_at' => '2030-02-31T12:00']))->assertSessionHasErrors('scheduled_at');
    $this->assertDatabaseCount('elections', 0);
});

test('the database rejects an overlapping open round and a candidate outside its composite foreign key', function () {
    $round = openElectionForTest();
    expect(fn () => DB::transaction(fn () => Round::create(['election_id' => $round->election_id, 'number' => 2, 'status' => 'aberto', 'opened_at' => now()])))
        ->toThrow(UniqueConstraintViolationException::class);
    $historical = Election::factory()->create(['completed_at' => now()]);
    $outside = Candidate::factory()->create(['election_id' => $historical->id]);
    $elector = User::factory()->create();
    expect(fn () => DB::transaction(fn () => Vote::create(['round_id' => $round->id, 'candidate_id' => $outside->id])))
        ->toThrow(QueryException::class);
    $this->assertDatabaseCount('votes', 0);
});
