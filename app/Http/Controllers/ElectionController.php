<?php

namespace App\Http\Controllers;

use App\Actions\RoundSummary;
use App\Actions\Voting;
use App\Http\Requests\StoreElectionRequest;
use App\Models\Election;
use App\Models\Participation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ElectionController extends Controller
{
    public function __construct(private Voting $voting, private RoundSummary $summary) {}

    public function index(Request $request): Response
    {
        $this->voting->transitionDue();

        return Inertia::render('welcome', [
            'snapshot' => fn () => $this->snapshot(),
            'history' => fn () => Election::whereNotNull('completed_at')->with(['rounds.candidates', 'candidates'])->orderByDesc('id')->paginate(10)
                ->through(fn (Election $election) => $this->electionData($election)),
            'ownVote' => fn () => $this->ownVote($request),
        ]);
    }

    public function admin(): Response
    {
        Gate::authorize('create', Election::class);
        $this->voting->transitionDue();

        return Inertia::render('admin-elections', ['snapshot' => fn () => $this->snapshot()]);
    }

    public function store(StoreElectionRequest $request): RedirectResponse
    {
        $this->voting->createElection($request->user(), $request->validated());

        return to_route('admin.elections')->with('success', 'Eleição criada. Os dados da eleição e dos candidatos são definitivos.');
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        $active = Election::whereNull('completed_at')->with(['rounds.candidates', 'candidates'])->first();
        $display = $active ?? Election::with(['rounds.candidates', 'candidates'])->orderByDesc('id')->first();

        return [
            'election' => $display ? $this->electionData($display) : null,
            'has_active_election' => $active !== null,
            'read_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function electionData(Election $election): array
    {
        return [
            'id' => $election->id, 'title' => $election->title,
            'completed_at' => $election->completed_at?->toIso8601String(),
            'candidates' => $election->candidates->map->only(['id', 'name'])->all(),
            'rounds' => $election->rounds->map(fn ($round) => $this->summary->for($round))->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function ownVote(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || ! $user->is_active) {
            return null;
        }
        $election = Election::orderByDesc('id')->first();
        $round = $election?->rounds()->reorder()->orderByDesc('number')->first();
        if (! $round) {
            return null;
        }
        $participated = Participation::where('round_id', $round->id)->where('user_id', $user->id)->exists();

        return $participated ? ['round_id' => $round->id] : null;
    }
}
