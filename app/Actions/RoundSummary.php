<?php

namespace App\Actions;

use App\Models\Round;
use Illuminate\Support\Facades\Storage;

class RoundSummary
{
    /** @return array<string, mixed> */
    public function for(Round $round): array
    {
        $round->loadMissing('candidates');
        $counts = $round->votes()->selectRaw('candidate_id, COUNT(*) AS total')->groupBy('candidate_id')->pluck('total', 'candidate_id');
        $total = (int) $counts->sum();
        $maximum = (int) ($counts->max() ?? 0);
        $leaders = $total === 0 ? [] : $counts->filter(fn ($count) => (int) $count === $maximum)->keys()->map(fn ($id) => (int) $id)->values()->all();

        return [
            'id' => $round->id, 'number' => $round->number, 'status' => $round->status,
            'scheduled_at' => $round->scheduled_at?->toIso8601String(),
            'opened_at' => $round->opened_at?->toIso8601String(),
            'closed_at' => $round->closed_at?->toIso8601String(),
            'selection_justification' => $round->selection_justification, 'total' => $total,
            'simulated_total' => $round->votes()->where('is_simulated', true)->count(),
            'outcome' => $round->status !== 'encerrado' ? 'pendente' : ($total === 0 ? 'falha' : (count($leaders) === 1 ? 'vencedor' : 'empate')),
            'leaders' => $leaders,
            'candidates' => $round->candidates->map(fn ($candidate) => [
                'id' => $candidate->id, 'name' => $candidate->name, 'photo_url' => $candidate->photo_path ? Storage::disk('public')->url($candidate->photo_path) : null,
                'votes' => (int) ($counts[$candidate->id] ?? 0),
                'percentage' => $total > 0 ? round(((int) ($counts[$candidate->id] ?? 0)) * 100 / $total, 1) : 0,
            ])->all(),
        ];
    }
}
