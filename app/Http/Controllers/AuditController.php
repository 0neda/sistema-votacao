<?php

namespace App\Http\Controllers;

use App\Actions\Voting;
use App\Http\Requests\AuditFilterRequest;
use App\Models\AuditLog;
use App\Models\Election;
use App\Models\Participation;
use App\Models\Round;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(AuditFilterRequest $request, Voting $voting): Response
    {
        Gate::authorize('audit', Election::class);
        $voting->transitionDue();
        $filters = $request->validated();
        $selected = null;
        if ($request->filled('round_id')) {
            $selected = Round::whereKey($request->integer('round_id'))->where('status', 'encerrado')->first();
            abort_unless($selected !== null, 403, 'As cédulas só estão disponíveis após o encerramento.');
            AuditLog::record('CONSULTA_CEDULAS', 'round', $selected->id);
        }
        $logs = AuditLog::with('user:id,name')
            ->when($request->filled('event'), fn ($query) => $query->where('event', $filters['event']))
            ->when($request->filled('from'), fn ($query) => $query->where('created_at', '>=', CarbonImmutable::parse($filters['from'], 'America/Sao_Paulo')->utc()))
            ->when($request->filled('until'), fn ($query) => $query->where('created_at', '<', CarbonImmutable::parse($filters['until'], 'America/Sao_Paulo')->addDay()->utc()))
            ->orderByDesc('id')->paginate(20, pageName: 'logs_page')->withQueryString()
            ->through(fn ($log) => [
                'id' => $log->id, 'created_at' => $log->created_at->toIso8601String(), 'actor' => $log->user_id === null ? 'Sistema' : $log->user->name,
                'event' => $log->event, 'target' => $log->target_type.($log->target_id ? ' #'.$log->target_id : ''),
                'result' => $log->result, 'ip_address' => $log->ip_address, 'metadata' => $log->metadata,
            ]);

        return Inertia::render('audit', [
            'logs' => $logs, 'filters' => $filters,
            'closedRounds' => Round::where('status', 'encerrado')->with('election:id,title')->orderByDesc('id')->get()
                ->map(fn ($round) => ['id' => $round->id, 'label' => $round->election->title.' — Turno '.$round->number]),
            'ballots' => $selected?->votes()->with('candidate:id,name')->orderBy('id')
                ->paginate(20, pageName: 'votes_page')->withQueryString()->through(fn ($vote) => [
                    'hash' => $vote->id, 'candidate' => $vote->candidate->name, 'is_simulated' => $vote->is_simulated,
                ]),
            'participants' => $selected ? Participation::where('round_id', $selected->id)->with('user:id,name,email')->orderBy('user_id')
                ->paginate(20, pageName: 'participants_page')->withQueryString()->through(fn ($participation) => [
                    'id' => $participation->user_id, 'name' => $participation->user->name, 'email' => $participation->user->email,
                ]) : null,

        ]);
    }
}
