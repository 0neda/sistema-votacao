<?php

namespace App\Http\Controllers;

use App\Actions\Voting;
use App\Http\Requests\StoreRoundRequest;
use App\Models\Election;
use App\Models\Round;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoundController extends Controller
{
    public function store(StoreRoundRequest $request, Election $election, Voting $voting): RedirectResponse
    {
        $voting->scheduleRound($request->user(), $election, $request->validated());

        return to_route('admin.elections')->with('success', 'Novo turno agendado.');
    }

    public function close(Request $request, Round $round, Voting $voting): RedirectResponse
    {
        $voting->closeRound($request->user(), $round);

        return to_route('admin.elections')->with('success', 'Turno encerrado. O resultado está disponível na central da eleição.');
    }
}
