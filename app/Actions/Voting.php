<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Election;
use App\Models\Participation;
use App\Models\Round;
use App\Models\User;
use App\Models\Vote;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class Voting
{
    public function __construct(private RoundSummary $summary, private StoreCandidatePhoto $photos) {}

    private function lockLifecycle(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(731028)');
        }
    }

    public function transitionDue(): void
    {
        try {
            DB::transaction(function (): void {
                $this->lockLifecycle();
                if (Round::where('status', 'aberto')->exists()) {
                    return;
                }
                $round = Round::where('status', 'agendado')->where('scheduled_at', '<=', now())
                    ->orderBy('scheduled_at')->orderBy('id')->lockForUpdate()->first();
                if ($round) {
                    $round->update(['status' => 'aberto', 'opened_at' => now()]);
                    AuditLog::record('ABERTURA_AUTOMATICA', 'round', $round->id, system: true);
                }
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'idx_single_open_round')) {
                throw $exception;
            }
        }
    }

    /** @param array<string, mixed> $data */
    public function createElection(User $actor, array $data): Election
    {
        Gate::forUser($actor)->authorize('create', Election::class);
        $photoPaths = [];
        try {
            foreach ($data['candidates'] as $index => $candidate) {
                $photoPaths[$index] = isset($candidate['photo']) ? $this->photos->store($candidate['photo']) : null;
            }

            return DB::transaction(function () use ($actor, $data, $photoPaths): Election {
                $this->lockLifecycle();
                $actor = User::whereKey($actor->id)->sharedLock()->firstOrFail();
                Gate::forUser($actor)->authorize('create', Election::class);
                if (Election::whereNull('completed_at')->exists()) {
                    throw ValidationException::withMessages(['election' => 'Finalize a eleição em andamento antes de criar outra.']);
                }
                $election = Election::create([
                    'title' => $data['title'],
                    'description' => '', 'created_by' => $actor->id,
                ]);
                foreach ($data['candidates'] as $index => $candidate) {
                    $election->candidates()->create([
                        'name' => trim($candidate['name']), 'description' => '', 'display_order' => $index + 1, 'photo_path' => $photoPaths[$index],
                    ]);
                }
                $immediate = $data['opening'] === 'imediata';
                $round = $election->rounds()->create([
                    'number' => 1, 'status' => $immediate ? 'aberto' : 'agendado',
                    'scheduled_at' => $immediate ? null : CarbonImmutable::parse($data['scheduled_at'])->utc(),
                    'opened_at' => $immediate ? now() : null,
                ]);
                $round->candidates()->attach($election->candidates()->pluck('id'));
                AuditLog::record('CRIACAO_ELEICAO', 'election', $election->id);
                AuditLog::record($immediate ? 'ABERTURA_MANUAL' : 'AGENDAMENTO_TURNO', 'round', $round->id, metadata: ['number' => 1]);

                return $election;
            }, attempts: 3);
        } catch (\Throwable $exception) {
            foreach (array_filter($photoPaths) as $path) {
                Storage::disk('public')->delete($path);
            }
            if (! $exception instanceof UniqueConstraintViolationException || ! str_contains($exception->getMessage(), 'idx_single_')) {
                throw $exception;
            }
            throw ValidationException::withMessages(['election' => 'Já existe uma eleição em andamento. Atualize a página.']);
        }
    }

    public function closeRound(User $actor, Round $round): void
    {
        Gate::forUser($actor)->authorize('close', $round);
        DB::transaction(function () use ($actor, $round): void {
            $this->lockLifecycle();
            Gate::forUser(User::whereKey($actor->id)->sharedLock()->firstOrFail())->authorize('close', $round);
            $election = Election::whereKey($round->election_id)->lockForUpdate()->firstOrFail();
            $locked = Round::whereKey($round->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'aberto') {
                throw ValidationException::withMessages(['round' => 'Somente um turno aberto pode ser encerrado.']);
            }
            $locked->update(['status' => 'encerrado', 'closed_at' => now()]);
            $result = $this->summary->for($locked);
            if (in_array($result['outcome'], ['vencedor', 'falha'], true)) {
                $election->update(['completed_at' => now()]);
            }
            AuditLog::record('ENCERRAMENTO_TURNO', 'round', $locked->id, metadata: ['outcome' => $result['outcome']]);
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function scheduleRound(User $actor, Election $election, array $data): Round
    {
        Gate::forUser($actor)->authorize('create', Election::class);

        return DB::transaction(function () use ($actor, $election, $data): Round {
            $this->lockLifecycle();
            Gate::forUser(User::whereKey($actor->id)->sharedLock()->firstOrFail())->authorize('create', Election::class);
            $locked = Election::whereKey($election->id)->lockForUpdate()->firstOrFail();
            $previous = $locked->rounds()->reorder()->orderByDesc('number')->lockForUpdate()->firstOrFail();
            if ($locked->completed_at || $previous->status !== 'encerrado') {
                throw ValidationException::withMessages(['round' => 'Não é possível agendar outro turno para esta eleição.']);
            }
            $result = $this->summary->for($previous);
            if ($result['outcome'] !== 'empate') {
                throw ValidationException::withMessages(['round' => 'Somente um empate permite novo turno.']);
            }
            $candidateIds = $result['leaders'];
            $justification = null;
            if ($previous->number >= 2) {
                $candidateIds = array_map('intval', $data['candidate_ids'] ?? []);
                $justification = trim($data['selection_justification'] ?? '');
                if (count($candidateIds) < 2 || count($candidateIds) > 3 || count(array_unique($candidateIds)) !== count($candidateIds)
                    || $locked->candidates()->whereIn('id', $candidateIds)->count() !== count($candidateIds) || $justification === '') {
                    throw ValidationException::withMessages(['candidate_ids' => 'Selecione de 2 a 3 candidatos originais e informe a justificativa.']);
                }
            }
            $round = $locked->rounds()->create([
                'number' => $previous->number + 1, 'status' => 'agendado',
                'scheduled_at' => CarbonImmutable::parse($data['scheduled_at'])->utc(),
                'selection_justification' => $justification,
            ]);
            $round->candidates()->attach($candidateIds);
            AuditLog::record('AGENDAMENTO_TURNO', 'round', $round->id, metadata: array_filter([
                'number' => $round->number, 'justification' => $justification,
            ]));

            return $round;
        }, attempts: 3);
    }

    public function vote(User $actor, Round $round, int $candidateId): void
    {
        $this->transitionDue();
        try {
            DB::transaction(function () use ($actor, $round, $candidateId): void {
                $locked = Round::whereKey($round->id)->lockForUpdate()->firstOrFail();
                $elector = User::whereKey($actor->id)->sharedLock()->firstOrFail();
                Gate::forUser($elector)->authorize('vote', $locked);
                if ($locked->status !== 'aberto') {
                    throw ValidationException::withMessages(['vote' => 'Este turno não está aberto para votação.']);
                }
                if (Participation::where('round_id', $locked->id)->where('user_id', $elector->id)->exists()) {
                    throw ValidationException::withMessages(['vote' => 'Seu voto já foi registrado']);
                }
                if (! $locked->candidates()->where('candidates.id', $candidateId)->exists()) {
                    throw ValidationException::withMessages(['vote' => 'O candidato não participa deste turno.']);
                }
                Participation::create(['round_id' => $locked->id, 'user_id' => $elector->id]);
                Vote::create(['round_id' => $locked->id, 'candidate_id' => $candidateId]);
                AuditLog::record('VOTO_ACEITO', 'round', $locked->id);
            }, attempts: 3);
        } catch (ValidationException $exception) {
            $duplicate = ($exception->errors()['vote'][0] ?? '') === 'Seu voto já foi registrado';
            AuditLog::record($duplicate ? 'VOTO_DUPLICADO' : 'VOTO_REJEITADO', 'round', $round->id, 'rejeitado', ['reason' => $duplicate ? 'duplicado' : 'turno_ou_candidato_invalido']);
            throw $exception;
        } catch (UniqueConstraintViolationException $exception) {
            if (! str_contains($exception->getMessage(), 'participations_pkey')) {
                throw $exception;
            }
            AuditLog::record('VOTO_DUPLICADO', 'round', $round->id, 'rejeitado', ['reason' => 'duplicado']);
            throw ValidationException::withMessages(['vote' => 'Seu voto já foi registrado']);
        }
    }
}
