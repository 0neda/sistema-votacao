<?php

namespace App\Console\Commands;

use App\Actions\Voting;
use App\Models\AuditLog;
use App\Models\Round;
use App\Models\Vote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SimulateVotes extends Command
{
    protected $signature = 'votacao:simular {candidato? : ID do candidato; omita para listar} {quantidade=1 : Quantidade de votos, entre 1 e 10000} {--turno= : ID do turno; por padrão usa o turno aberto}';

    protected $description = 'Adiciona votos marcados como simulados, somente em ambiente local ou de testes';

    public function handle(Voting $voting): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Simulações são permitidas somente em ambiente local ou de testes.');

            return self::FAILURE;
        }
        $voting->transitionDue();
        $roundId = $this->option('turno');
        if ($roundId !== null && ((! is_string($roundId) && ! is_int($roundId)) || ! ctype_digit((string) $roundId) || (int) $roundId < 1)) {
            $this->error('Informe um ID de turno válido.');

            return self::FAILURE;
        }
        $round = $roundId === null ? Round::where('status', 'aberto')->first() : Round::find((int) $roundId);
        if (! $round || $round->status !== 'aberto') {
            $this->error('Selecione um turno aberto para simular votos.');

            return self::FAILURE;
        }
        if ($this->argument('candidato') === null) {
            $this->info('Candidatos do turno #'.$round->id.':');
            $this->table(['ID', 'Candidato'], $round->candidates->map(fn ($candidate) => [$candidate->id, $candidate->name])->all());
            $this->line('Uso: php artisan votacao:simular ID QUANTIDADE');

            return self::SUCCESS;
        }
        $candidateInput = $this->argument('candidato');
        $quantityInput = $this->argument('quantidade');
        if ((! is_string($candidateInput) && ! is_int($candidateInput)) || (! is_string($quantityInput) && ! is_int($quantityInput))) {
            $this->error('Informe candidato e quantidade como números inteiros.');

            return self::FAILURE;
        }
        $candidateId = (string) $candidateInput;
        $quantity = (string) $quantityInput;
        if (! ctype_digit($candidateId) || (int) $candidateId < 1 || ! ctype_digit($quantity) || (int) $quantity < 1 || (int) $quantity > 10000) {
            $this->error('Informe um candidato válido e uma quantidade inteira entre 1 e 10000.');

            return self::FAILURE;
        }
        try {
            DB::transaction(function () use ($round, $candidateId, $quantity): void {
                $locked = Round::whereKey($round->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'aberto' || ! $locked->candidates()->where('candidates.id', (int) $candidateId)->exists()) {
                    throw ValidationException::withMessages(['simulation' => 'O turno precisa estar aberto e o candidato deve participar dele.']);
                }
                $rows = [];
                for ($index = 0; $index < (int) $quantity; $index++) {
                    $rows[] = ['id' => bin2hex(random_bytes(32)), 'round_id' => $locked->id, 'candidate_id' => (int) $candidateId, 'is_simulated' => true];
                }
                foreach (array_chunk($rows, 500) as $chunk) {
                    Vote::insert($chunk);
                }
                AuditLog::record('SIMULACAO_VOTOS', 'round', $locked->id, metadata: ['quantity' => (int) $quantity], system: true);
            }, attempts: 3);
        } catch (ValidationException $exception) {
            $this->error($exception->errors()['simulation'][0]);

            return self::FAILURE;
        }
        $this->info($quantity.' votos simulados adicionados ao turno #'.$round->id.'. A simulação está sinalizada no placar e na auditoria.');

        return self::SUCCESS;
    }
}
